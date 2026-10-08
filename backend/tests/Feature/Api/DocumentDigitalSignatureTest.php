<?php

namespace Tests\Feature\Api;

use App\Jobs\SignDocument;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\DocumentType;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentBatchService;
use App\Services\DocumentService;
use App\Services\ReportsService;
use App\Services\SignatureCertificateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

/**
 * La firma digital (PAdES) de la empresa convive con la conformidad del
 * trabajador: contrato JSON de la API, reintento, verificación, filtros,
 * huérfanos, reportes y auditoría.
 */
class DocumentDigitalSignatureTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private Tenant $tenant;
    private DocumentType $docType;
    private User $worker;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('documents');
        Storage::fake('certificates');
        config(['services.signer.base_url' => 'http://signer.test']);

        // ReportsService agrupa por mes con YEAR()/MONTH() de MySQL (ver ReportsControllerTest).
        DB::connection()->getPdo()->sqliteCreateFunction('YEAR', fn (?string $d) => $d ? (int) date('Y', strtotime($d)) : null);
        DB::connection()->getPdo()->sqliteCreateFunction('MONTH', fn (?string $d) => $d ? (int) date('n', strtotime($d)) : null);

        $this->tenant = Tenant::factory()->create(['ruc' => '20603839961']);
        $this->docType = DocumentType::factory()->create();
        $this->worker = User::factory()->client()->withTenantRole($this->tenant, 'client', true)->create(['status' => 'active']);
        $this->admin = User::factory()->admin()->withTenantRole($this->tenant, 'admin', true)->create(['status' => 'active']);
    }

    private function enableGlobalSignature(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);
        app(SignatureCertificateService::class)->storeCertificate(
            $root, $this->makePfx($this->defaultDn('20603839961'), 'secret', 365), 'secret', 'https://tsa.example/ts'
        );
        SignatureSettings::current()->update(['signature_enabled' => true]);
    }

    private function doc(array $attrs = []): Document
    {
        $doc = Document::factory()->create($attrs + [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->worker->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
            'file_path' => 'doc.pdf',
        ]);
        Storage::disk('documents')->put('doc.pdf', '%PDF-1.4 dummy');

        return $doc;
    }

    private function digital(array $extra = []): array
    {
        return $extra + [
            'method' => 'pades_pyhanko', 'certificate_source' => 'global', 'certificate_ruc' => '20603839961',
            'signer_subject' => 'CN=X', 'includes_conformity' => false,
            'first_signed_at' => '2026-10-06T10:00:00-05:00', 'resign_count' => 0,
        ];
    }

    private function workerSigned(array $signature = []): array
    {
        return [
            'status' => 'signed',
            'signed_at' => now(),
            'signature' => $signature + [
                'verification_method' => 'email_2fa', 'user_name' => 'Ana', 'pdf_mark' => 'pades',
                'document_sha256' => 'abc123', 'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    // ---------------- DocumentResource ----------------

    public function test_el_resource_expone_los_campos_digitales_y_oculta_el_error_al_trabajador(): void
    {
        $doc = $this->doc($this->workerSigned() + [
            'digital_signature' => $this->digital(), 'digitally_signed_at' => now(),
            'digital_signature_status' => 'failed', 'digital_signature_error' => 'Sello de tiempo no disponible: x',
            'original_file_path' => '.originals/doc.pdfa.pdf', 'original_has_conformity' => true,
        ]);

        $asWorker = $this->actingAs($this->worker)->getJson("/api/documents/{$doc->id}")->assertOk();
        $asWorker->assertJsonPath('data.digital_signature.method', 'pades_pyhanko')
            ->assertJsonPath('data.digital_signature_status', 'failed')
            ->assertJsonPath('data.digital_signature_error', null)
            ->assertJsonPath('data.status', 'signed')
            ->assertJsonMissingPath('data.original_file_path')
            ->assertJsonMissingPath('data.original_has_conformity')
            ->assertJsonMissingPath('data.signature.document_sha256');
        $this->assertNotNull($asWorker->json('data.digitally_signed_at'));

        $asAdmin = $this->actingAs($this->admin)->getJson("/api/documents/{$doc->id}")->assertOk();
        $asAdmin->assertJsonPath('data.digital_signature_error', 'Sello de tiempo no disponible: x')
            ->assertJsonPath('data.signature.document_sha256', 'abc123')
            ->assertJsonMissingPath('data.original_file_path');
    }

    public function test_el_listado_no_filtra_el_detalle_interno_al_trabajador(): void
    {
        $this->doc($this->workerSigned() + [
            'digital_signature' => $this->digital(), 'digital_signature_status' => 'failed',
            'digital_signature_error' => 'detalle interno', 'original_file_path' => '.originals/doc.pdfa.pdf',
        ]);

        $row = $this->actingAs($this->worker)->getJson('/api/documents?my_documents=true')->assertOk()->json('data.0');

        $this->assertSame('failed', $row['digital_signature_status']);
        $this->assertArrayNotHasKey('digital_signature_error', $row);
        $this->assertArrayNotHasKey('original_file_path', $row);
        $this->assertArrayNotHasKey('document_sha256', $row['signature']);

        $adminRow = $this->actingAs($this->admin)->getJson('/api/documents')->assertOk()->json('data.0');
        $this->assertSame('detalle interno', $adminRow['digital_signature_error']);
    }

    public function test_filtro_digital_status(): void
    {
        $signed = $this->doc(['employee_document_number' => '1', 'digital_signature' => $this->digital(), 'digital_signature_status' => 'signed']);
        $pending = $this->doc(['employee_document_number' => '2', 'digital_signature_status' => 'pending']);
        $failed = $this->doc(['employee_document_number' => '3', 'digital_signature' => $this->digital(), 'digital_signature_status' => 'failed']);
        $none = $this->doc(['employee_document_number' => '4']);

        $ids = fn (string $f) => collect($this->actingAs($this->admin)->getJson("/api/documents?digital_status={$f}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$signed->id, $failed->id], $ids('signed'));
        $this->assertSame([$pending->id], $ids('pending'));
        $this->assertSame([$failed->id], $ids('failed'));
        $this->assertSame([$none->id], $ids('none'));
    }

    // ---------------- sign-digital ----------------

    public function test_sign_digital_sobre_un_documento_firmado_por_el_trabajador_encola_en_prioridad(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $doc = $this->doc($this->workerSigned());

        $this->actingAs($this->admin)->postJson("/api/documents/{$doc->id}/sign-digital")
            ->assertStatus(202)
            ->assertJsonPath('digital_signature_status', 'pending');

        $fresh = $doc->fresh();
        $this->assertSame('pending', $fresh->digital_signature_status);
        $this->assertSame('signed', $fresh->status);
        Queue::assertPushedOn('signing-priority', SignDocument::class, fn ($j) => $j->documentId === $doc->id);
    }

    public function test_sign_digital_sirve_de_reintento_de_la_refirma_fallida(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $doc = $this->doc($this->workerSigned() + [
            'digital_signature' => $this->digital(), 'digital_signature_status' => 'failed',
            'digital_signature_error' => 'boom',
        ]);

        $this->actingAs($this->admin)->postJson("/api/documents/{$doc->id}/sign-digital")->assertStatus(202);

        $fresh = $doc->fresh();
        $this->assertSame('pending', $fresh->digital_signature_status);
        $this->assertNull($fresh->digital_signature_error);
        Queue::assertPushedOn('signing-priority', SignDocument::class);
    }

    public function test_sign_digital_con_la_firma_al_dia_responde_422(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $doc = $this->doc(['digital_signature' => $this->digital(), 'digital_signature_status' => 'signed']);

        $this->actingAs($this->admin)->postJson("/api/documents/{$doc->id}/sign-digital")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La firma digital de la empresa ya está al día.');
        Queue::assertNothingPushed();
    }

    public function test_sign_digital_exige_permiso(): void
    {
        $this->enableGlobalSignature();
        $doc = $this->doc();

        $this->actingAs($this->worker)->postJson("/api/documents/{$doc->id}/sign-digital")->assertStatus(403);
    }

    // ---------------- verify-signature ----------------

    public function test_verify_signature_usa_digital_signature_e_incluye_includes_conformity(): void
    {
        Http::fake(['*/verify' => Http::response(['success' => true, 'verification' => [
            'intact' => true, 'valid' => true, 'trusted' => false, 'covers_whole_file' => true,
        ]])]);
        $doc = $this->doc($this->workerSigned() + ['digital_signature' => $this->digital(['includes_conformity' => true])]);

        $this->actingAs($this->worker)->getJson("/api/documents/{$doc->id}/verify-signature")
            ->assertOk()
            ->assertJsonPath('data.verifiable', true)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.includes_conformity', true);
    }

    public function test_verify_signature_sin_firma_digital_pero_con_conformidad_explica_que_no_es_criptografica(): void
    {
        Http::fake();
        $doc = $this->doc($this->workerSigned());

        $res = $this->actingAs($this->worker)->getJson("/api/documents/{$doc->id}/verify-signature")->assertOk();

        $res->assertJsonPath('data.verifiable', false)->assertJsonPath('data.reason', 'not_cryptographically_signed');
        $this->assertStringContainsString('no tiene firma digital de la empresa', $res->json('data.message'));
        $this->assertStringContainsString('no es una firma criptográfica', $res->json('data.message'));
        Http::assertNothingSent();
    }

    public function test_verify_signature_sin_ninguna_firma(): void
    {
        $doc = $this->doc();

        $res = $this->actingAs($this->worker)->getJson("/api/documents/{$doc->id}/verify-signature")->assertOk();

        $res->assertJsonPath('data.reason', 'not_cryptographically_signed');
        $this->assertStringNotContainsString('conformidad', $res->json('data.message'));
    }

    // ---------------- 2FA: contrato de la respuesta y polling ----------------

    public function test_signature_status_expone_el_estado_de_la_firma_digital_para_el_polling(): void
    {
        $doc = $this->doc(['digital_signature' => $this->digital(['includes_conformity' => true]),
            'digital_signature_status' => 'pending', 'digitally_signed_at' => now()]);

        $this->actingAs($this->worker)->getJson("/api/documents/{$doc->id}/signature-status")
            ->assertOk()
            ->assertJsonPath('digital_signature_status', 'pending')
            ->assertJsonPath('includes_conformity', true)
            ->assertJsonStructure(['document_id', 'requires_signature', 'is_signed', 'signed_at', 'signature', 'digitally_signed_at']);
    }

    // ---------------- Huérfanos ----------------

    public function test_asignar_un_huerfano_encola_la_firma_digital_si_aplica(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $orphan = $this->doc(['status' => 'orphan', 'user_id' => null]);

        $result = app(DocumentService::class)->assignOrphanDocument($orphan, $this->worker);

        $this->assertSame('pending', $result->status);
        $this->assertSame('pending', $orphan->fresh()->digital_signature_status);
        Queue::assertPushedOn('signing', SignDocument::class, fn ($j) => $j->documentId === $orphan->id);
    }

    public function test_asignar_un_huerfano_sin_firma_activada_no_encola(): void
    {
        Queue::fake();
        $orphan = $this->doc(['status' => 'orphan', 'user_id' => null]);

        app(DocumentService::class)->assignOrphanDocument($orphan, $this->worker);

        $this->assertNull($orphan->fresh()->digital_signature_status);
        Queue::assertNothingPushed();
    }

    public function test_eliminar_con_archivo_borra_tambien_la_base_de_refirma(): void
    {
        $doc = $this->doc(['original_file_path' => '.originals/doc.pdfa.pdf']);
        Storage::disk('documents')->put('.originals/doc.pdfa.pdf', '%PDF-base');

        app(DocumentService::class)->deleteDocument($doc->id, $this->admin, true);

        Storage::disk('documents')->assertMissing('doc.pdf');
        Storage::disk('documents')->assertMissing('.originals/doc.pdfa.pdf');
    }

    // ---------------- Reportes y lotes ----------------

    public function test_estadisticas_cuentan_firmados_digitalmente_y_fallidos(): void
    {
        $this->doc(['employee_document_number' => '1', 'digital_signature' => $this->digital(), 'digital_signature_status' => 'signed']);
        $this->doc(['employee_document_number' => '2', 'digital_signature' => $this->digital(), 'digital_signature_status' => 'failed']);
        $this->doc(['employee_document_number' => '3'] + $this->workerSigned());
        $this->doc(['employee_document_number' => '4']);

        $stats = app(ReportsService::class)->getDocumentStats($this->tenant->id);

        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['signed']);
        $this->assertSame(2, $stats['digitally_signed']);
        $this->assertSame(1, $stats['digital_failed']);
    }

    public function test_export_de_documentos_separa_conformidad_y_firma_digital(): void
    {
        $this->doc(['employee_document_number' => '1', 'digital_signature' => $this->digital(),
            'digitally_signed_at' => '2026-10-06 12:00:00', 'digital_signature_status' => 'signed'] + $this->workerSigned());
        $this->doc(['employee_document_number' => '2', 'digital_signature_status' => 'pending']);
        $this->doc(['employee_document_number' => '3', 'digital_signature' => $this->digital(), 'digital_signature_status' => 'failed']);
        $this->doc(['employee_document_number' => '4']);

        $rows = app(ReportsService::class)->getDocumentReportData(['tenant_id' => $this->tenant->id])
            ->keyBy(fn ($r) => $r['estado'] . '|' . $r['firma_digital']);

        $this->assertTrue($rows->has('Firmado por el trabajador|Sí 06/10/2026'));
        $this->assertTrue($rows->has('Pendiente|Pendiente'));
        $this->assertTrue($rows->has('Pendiente|Error'));
        $this->assertTrue($rows->has('Pendiente|No'));
        $this->assertArrayHasKey('conformidad_trabajador', $rows->first());
        $this->assertArrayNotHasKey('fecha_firma', $rows->first());
    }

    public function test_resumen_del_lote_cuenta_los_firmados_digitalmente(): void
    {
        $batch = DocumentBatch::create([
            'tenant_id' => $this->tenant->id, 'uploaded_by' => $this->admin->id, 'type_id' => $this->docType->id,
            'period' => '2026-10', 'original_filename' => 'lote.zip', 'total_files' => 2,
        ]);
        $this->doc(['employee_document_number' => '1', 'batch_id' => $batch->id, 'digital_signature' => $this->digital(), 'digital_signature_status' => 'signed']);
        $this->doc(['employee_document_number' => '2', 'batch_id' => $batch->id]);

        $data = app(DocumentBatchService::class)->transformBatchForDetail($batch->load('documents'));

        $this->assertSame(1, $data['documents_summary']['digitally_signed']);
        $this->assertSame(2, $data['documents_summary']['total']);
    }

    // ---------------- Auditoría ----------------

    public function test_la_accion_de_auditoria_existe_es_siempre_activa_y_tiene_etiqueta(): void
    {
        $this->assertSame('document.digitally_signed', AuditLog::ACTION_DOCUMENT_DIGITALLY_SIGNED);
        $this->assertContains(AuditLog::ACTION_DOCUMENT_DIGITALLY_SIGNED, AuditLog::ALWAYS_ON);

        $log = app(\App\Services\AuditService::class)->logDocumentDigitallySigned(5, null, [
            'certificate_source' => 'tenant', 'includes_conformity' => true, 'resign' => true,
            'tenant_id' => $this->tenant->id, 'certificate_password' => 'NO-DEBE-GUARDARSE',
        ]);

        $this->assertSame('Firma digital (certificado) aplicada', $log->description);
        $this->assertSame(['certificate_source', 'includes_conformity', 'resign', 'tenant_id'], array_keys($log->metadata));
    }
}
