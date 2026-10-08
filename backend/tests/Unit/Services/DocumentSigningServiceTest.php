<?php

namespace Tests\Unit\Services;

use App\Exceptions\DocumentSigningException;
use App\Models\Document;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\TenantSignatureCertificate;
use App\Models\User;
use App\Services\DocumentSigningService;
use App\Services\SignatureCertificateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

class DocumentSigningServiceTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private User $root;
    private Tenant $tenant;
    private SignatureCertificateService $certs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('certificates');
        Storage::fake('documents');
        config(['services.signer.base_url' => 'http://signer.test']);

        $this->certs = app(SignatureCertificateService::class);
        $this->root = User::factory()->root()->create(['status' => 'active']);
        $this->tenant = Tenant::factory()->create(['ruc' => '20603839961']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function globalCert(int $days = 365): void
    {
        $this->certs->storeCertificate($this->root, $this->makePfx($this->defaultDn('20603839961'), 'secret', $days), 'secret', 'https://tsa.example/ts');
        SignatureSettings::current()->update(['signature_enabled' => true]);
    }

    private function tenantCert(?Tenant $tenant = null, string $ruc = '20603839961', int $days = 365, ?string $tsaUrl = null): TenantSignatureCertificate
    {
        return $this->certs->storeTenantCertificate($this->root, $tenant ?? $this->tenant, $this->makePfx($this->defaultDn($ruc), 'secret', $days), 'secret', $tsaUrl);
    }

    /** Documento en cola de firma digital (digital_signature_status='pending'). */
    private function document(array $attrs = []): Document
    {
        $user = User::factory()->withTenantRole($this->tenant, 'client', true)->create(['status' => 'active']);
        $doc = Document::factory()->create($attrs + [
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'uploaded_by' => $user->id,
            'file_path' => 'doc.pdf',
            'digital_signature_status' => 'pending',
        ]);
        Storage::disk('documents')->put('doc.pdf', '%PDF-1.4 dummy');

        return $doc;
    }

    /** Conformidad del trabajador ya registrada (sin tocar status por el sidecar). */
    private function workerSigned(Document $doc, array $signature = []): Document
    {
        $doc->update([
            'status' => 'signed',
            'signed_at' => now(),
            'signature' => $signature + [
                'verification_method' => 'email_2fa', 'user_name' => 'Jorge Vásquez',
                'timestamp' => '2026-10-06T20:30:00+00:00', 'pdf_mark' => 'pades',
            ],
        ]);

        return $doc->fresh();
    }

    /**
     * Sidecar simulado según el contrato §3: escribe output_path, y la base
     * (base_output_path) si se la piden; conformity_applied solo si recibió
     * conformity. $apply=false simula un sidecar viejo que no lo informa.
     */
    private function fakeSigner(bool $apply = true): void
    {
        Http::fake([
            '*/sign' => function ($request) use ($apply) {
                file_put_contents($request['output_path'], '%PDF-signed');
                $baseWritten = false;
                if (!empty($request['base_output_path'])) {
                    @mkdir(dirname($request['base_output_path']), 0777, true);
                    file_put_contents($request['base_output_path'], '%PDF-base');
                    $baseWritten = true;
                }

                return Http::response(['success' => true, 'signature' => [
                    'signer_subject' => 'X', 'signing_time' => now()->toIso8601String(), 'tsa_applied' => false,
                    'digest_algo' => 'sha256', 'sha256_of_signed_file' => 'abc', 'covers_whole_file' => true,
                    'intact' => true, 'valid' => true, 'trusted' => false,
                    'conformity_applied' => $apply && isset($request['conformity']),
                    'base_written' => $baseWritten,
                ]]);
            },
            '*/extract-base' => function ($request) {
                @mkdir(dirname($request['output_path']), 0777, true);
                file_put_contents($request['output_path'], '%PDF-extracted');

                return Http::response(['success' => true, 'output_path' => $request['output_path']]);
            },
        ]);
    }

    /** Última petición enviada a un endpoint del sidecar. */
    private function lastRequest(string $endpoint)
    {
        $found = null;
        foreach (Http::recorded() as [$request]) {
            if (str_ends_with($request->url(), $endpoint)) {
                $found = $request;
            }
        }

        return $found;
    }

    /** Documento ya firmado con PAdES por la empresa (global), con base en .originals. */
    private function digitallySigned(array $meta = [], array $attrs = []): Document
    {
        $doc = $this->document($attrs);
        Storage::disk('documents')->put($doc->originalStoragePath(), '%PDF-base');
        $doc->update([
            'original_file_path' => $doc->originalStoragePath(),
            'digital_signature' => $meta + [
                'method' => 'pades_pyhanko', 'certificate_source' => 'global', 'certificate_ruc' => '20603839961',
                'includes_conformity' => false, 'conformity_signed_at' => null,
                'first_signed_at' => '2026-10-06T10:00:00-05:00', 'resign_count' => 0,
            ],
            'digitally_signed_at' => now(),
            'digital_signature_status' => 'pending',
        ]);

        return $doc->fresh();
    }

    private function sentCertificatePaths(): array
    {
        $paths = [];
        Http::assertSent(function ($request) use (&$paths) {
            $paths[] = $request['certificate_path'];
            return true;
        });

        return $paths;
    }

    public function test_firma_con_el_certificado_de_la_empresa(): void
    {
        $this->globalCert();
        // RUC distinto a propósito: no debe dejar ninguna marca en el documento.
        $record = $this->tenantCert(ruc: '20100000001');
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $this->assertSame([Storage::disk('certificates')->path($record->certificate_path)], $this->sentCertificatePaths());
        $sig = $doc->fresh()->digital_signature;
        $this->assertSame('tenant', $sig['certificate_source']);
        $this->assertSame('20100000001', $sig['certificate_ruc']);
        $this->assertArrayHasKey('certificate_organization', $sig);
        $this->assertArrayNotHasKey('certificate_ruc_mismatch', $sig);
        $this->assertSame('pades_pyhanko', $sig['method']);
    }

    public function test_por_defecto_no_pide_sello_visible_del_pie(): void
    {
        $this->globalCert();
        $doc = $this->document();
        Http::fake(['*/sign' => function ($request) {
            file_put_contents($request['output_path'], '%PDF-signed');
            file_put_contents($request['base_output_path'], '%PDF-base');

            return Http::response(['success' => true, 'signature' => [
                'signer_subject' => 'X', 'signing_time' => now()->toIso8601String(), 'tsa_applied' => false,
                'stamp_applied' => false,
            ]]);
        }]);

        app(DocumentSigningService::class)->signDocument($doc);

        Http::assertSent(fn ($r) => $r['visible'] === false);
        $this->assertFalse($doc->fresh()->digital_signature['stamp_applied']);
    }

    public function test_envia_visible_true_y_guarda_signer_details_y_stamp_applied(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $details = [
            'name' => 'BASILIO VENTURA WILLIAM', 'organization' => 'OVERHEAD MEN S.A.C.',
            'ruc' => '20603839961', 'title' => 'GERENTE GENERAL', 'country' => 'PE',
            'locality' => 'LIMA', 'signed_at_local' => '06/10/2026 16:24',
        ];
        Http::fake(['*/sign' => function ($request) use ($details) {
            file_put_contents($request['output_path'], '%PDF-signed');
            file_put_contents($request['base_output_path'], '%PDF-base');

            return Http::response(['success' => true, 'signature' => [
                'signer_subject' => 'X', 'signing_time' => now()->toIso8601String(), 'tsa_applied' => false,
                'stamp_applied' => true, 'signer_details' => $details,
            ]]);
        }]);

        config(['signature.visible_stamp' => true]);
        app(DocumentSigningService::class)->signDocument($doc);

        Http::assertSent(fn ($r) => $r['visible'] === true);
        $sig = $doc->fresh()->digital_signature;
        $this->assertTrue($sig['stamp_applied']);
        $this->assertSame($details, $sig['signer_details']);
    }

    public function test_sidecar_antiguo_sin_signer_details_guarda_null_y_false(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $sig = $doc->fresh()->digital_signature;
        $this->assertFalse($sig['stamp_applied']);
        $this->assertNull($sig['signer_details']);
    }

    public function test_empresa_sin_certificado_propio_usa_el_global_y_su_tsa(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $globalPath = Storage::disk('certificates')->path(SignatureSettings::current()->certificate_path);
        $this->assertSame([$globalPath], $this->sentCertificatePaths());
        $this->assertSame('global', $doc->fresh()->digital_signature['certificate_source']);
        Http::assertSent(fn ($r) => $r['tsa_url'] === 'https://tsa.example/ts');
    }

    public function test_empresa_con_tsa_propia_envia_la_tsa_de_la_empresa(): void
    {
        $this->globalCert();
        $this->tenantCert(tsaUrl: 'https://tsa.empresa.example/tsr');
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        Http::assertSent(fn ($r) => $r['tsa_url'] === 'https://tsa.empresa.example/tsr');
    }

    public function test_empresa_con_certificado_propio_sin_tsa_usa_la_global(): void
    {
        $this->globalCert();
        $this->tenantCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        Http::assertSent(fn ($r) => $r['tsa_url'] === 'https://tsa.example/ts');
    }

    public function test_documento_con_certificado_global_no_usa_la_tsa_de_otra_empresa(): void
    {
        $this->globalCert();
        $otra = Tenant::factory()->create(['ruc' => '20100000001']);
        $this->tenantCert($otra, '20100000001', tsaUrl: 'https://tsa.otra.example/tsr');
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $this->assertSame('global', $doc->fresh()->digital_signature['certificate_source']);
        Http::assertSent(fn ($r) => $r['tsa_url'] === 'https://tsa.example/ts');
    }

    public function test_binario_de_empresa_faltante_no_cae_al_global(): void
    {
        $this->globalCert();
        $record = $this->tenantCert();
        Storage::disk('certificates')->delete($record->certificate_path);
        $doc = $this->document();
        $this->fakeSigner();

        try {
            app(DocumentSigningService::class)->assertEligible($doc);
            $this->fail('Debió lanzar DocumentSigningException');
        } catch (DocumentSigningException $e) {
            $this->assertStringContainsString('de la empresa no está disponible', $e->getMessage());
        }

        // signDocument() es quien envía certificate_path al sidecar: tampoco debe caer al global.
        try {
            app(DocumentSigningService::class)->signDocument($doc);
            $this->fail('Debió lanzar DocumentSigningException');
        } catch (DocumentSigningException $e) {
            $this->assertStringContainsString('de la empresa no está disponible', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_certificado_de_empresa_vencido(): void
    {
        $this->globalCert();
        $this->tenantCert(days: 1);
        $doc = $this->document();
        Carbon::setTestNow(now()->addDays(2));

        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessageMatches('/venció el/');

        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_certificado_global_vencido(): void
    {
        $this->globalCert(1);
        $doc = $this->document();
        Carbon::setTestNow(now()->addDays(2));

        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessageMatches('/global venció el/');

        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_sin_ningun_certificado_con_firma_activada_a_la_fuerza(): void
    {
        SignatureSettings::current()->update(['signature_enabled' => true]);
        $doc = $this->document();

        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessage('No hay un certificado');

        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_resolve_for_tenant_null_devuelve_el_global(): void
    {
        $this->globalCert();
        $this->tenantCert();

        $c = $this->certs->resolveForTenant(null);

        $this->assertSame('global', $c->source);
        $this->assertSame('secret', $c->password);
        $this->assertSame('tenant', $this->certs->resolveForTenant($this->tenant->id)->source);
    }

    public function test_resolve_for_tenant_sin_nada_devuelve_null(): void
    {
        $this->assertNull($this->certs->resolveForTenant($this->tenant->id));
    }

    // ==========================================================
    // Firma digital de la empresa vs. conformidad del trabajador
    // ==========================================================

    public function test_firma_digital_no_toca_status_signature_ni_signed_at(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $fresh = $doc->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNull($fresh->signature);
        $this->assertNull($fresh->signed_at);
        $this->assertSame('pades_pyhanko', $fresh->digital_signature['method']);
        $this->assertNotNull($fresh->digitally_signed_at);
        $this->assertSame('signed', $fresh->digital_signature_status);
        $this->assertNull($fresh->digital_signature_error);
        $this->assertFalse($fresh->digital_signature['includes_conformity']);
        $this->assertNull($fresh->digital_signature['conformity_signed_at']);
        $this->assertSame(0, $fresh->digital_signature['resign_count']);
        $this->assertNotNull($fresh->digital_signature['first_signed_at']);
        $this->assertSame('%PDF-signed', Storage::disk('documents')->get('doc.pdf'));
    }

    public function test_primera_firma_envia_base_output_path_y_deja_la_base(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $req = $this->lastRequest('/sign');
        $docsDisk = Storage::disk('documents');
        $this->assertSame($docsDisk->path('doc.pdf'), $req['input_path']);
        $this->assertSame($docsDisk->path('.originals/doc.pdfa.pdf'), $req['base_output_path']);
        $this->assertArrayNotHasKey('skip_normalize', $req->data());
        $this->assertArrayNotHasKey('conformity', $req->data());

        $fresh = $doc->fresh();
        $this->assertSame('.originals/doc.pdfa.pdf', $fresh->original_file_path);
        $this->assertFalse($fresh->original_has_conformity);
        $this->assertTrue($docsDisk->exists('.originals/doc.pdfa.pdf'));
    }

    public function test_resfirma_parte_de_la_base_con_skip_normalize_y_envia_conformity(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned());
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $req = $this->lastRequest('/sign');
        $this->assertSame(Storage::disk('documents')->path('.originals/doc.pdfa.pdf'), $req['input_path']);
        $this->assertTrue($req['skip_normalize']);
        $this->assertArrayNotHasKey('base_output_path', $req->data());
        $this->assertSame('Jorge Vásquez', $req['conformity']['name']);
        $this->assertSame('2026-10-06T15:30:00-05:00', $req['conformity']['date_text']);

        $fresh = $doc->fresh();
        $this->assertTrue($fresh->digital_signature['includes_conformity']);
        $this->assertNotNull($fresh->digital_signature['conformity_signed_at']);
        $this->assertSame(1, $fresh->digital_signature['resign_count']);
        // Se conserva la primera fecha de firma.
        $this->assertSame('2026-10-06T10:00:00-05:00', $fresh->digital_signature['first_signed_at']);
        $this->assertSame('signed', $fresh->digital_signature_status);
        $this->assertSame('signed', $fresh->status);
    }

    public function test_conformity_usa_el_layout_del_page_size_del_lote(): void
    {
        $this->globalCert();
        $docType = \App\Models\DocumentType::factory()->create();
        $batch = \App\Models\DocumentBatch::create([
            'tenant_id' => $this->tenant->id, 'uploaded_by' => $this->root->id, 'type_id' => $docType->id,
            'period' => '2026-10', 'original_filename' => 'lote.zip', 'total_files' => 1, 'page_size' => 'a4',
        ]);
        $doc = $this->workerSigned($this->digitallySigned(attrs: ['batch_id' => $batch->id]));
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $layout = $this->lastRequest('/sign')['conformity']['layout'];
        $this->assertSame('absolute', $layout['mode']);
        $this->assertEquals(137.0, $layout['x_mm']);
        $this->assertEquals(238.8, $layout['name_y_mm']);
        $this->assertEquals(56.0, $layout['width_mm']);
        $this->assertSame('C', $layout['align']);
        $this->assertEquals(16.0, $layout['name_font_size']);
        $this->assertEquals(7.0, $layout['name_height_mm']);
        $this->assertEquals(5.0, $layout['date_offset_y_mm']);
        $this->assertEquals(8.0, $layout['date_font_size']);
    }

    public function test_sin_eleccion_explicita_envia_layouts_y_dimensiones(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned());
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $conf = $this->lastRequest('/sign')['conformity'];
        $this->assertEquals(119.0, $conf['layout']['name_y_mm']);
        $this->assertEqualsCanonicalizing(['a10', 'a4', 'a5', 'letter'], array_keys($conf['layouts']));
        $this->assertEquals(238.8, $conf['layouts']['a4']['name_y_mm']);
        $this->assertEquals([210.0, 297.0], $conf['page_dimensions_mm']['a4']);
    }

    public function test_con_eleccion_explicita_no_envia_layouts(): void
    {
        $this->globalCert();
        $docType = \App\Models\DocumentType::factory()->create();
        $batch = \App\Models\DocumentBatch::create([
            'tenant_id' => $this->tenant->id, 'uploaded_by' => $this->root->id, 'type_id' => $docType->id,
            'period' => '2026-10', 'original_filename' => 'lote.zip', 'total_files' => 1, 'page_size' => 'a4',
        ]);
        $doc = $this->workerSigned($this->digitallySigned(attrs: ['batch_id' => $batch->id]));
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $conf = $this->lastRequest('/sign')['conformity'];
        $this->assertArrayNotHasKey('layouts', $conf);
        $this->assertArrayNotHasKey('page_dimensions_mm', $conf);
    }

    public function test_sin_conformity_applied_lanza_y_deja_el_archivo_intacto(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned());
        Storage::disk('documents')->put('doc.pdf', '%PDF-valido-anterior');
        $this->fakeSigner(apply: false);

        try {
            app(DocumentSigningService::class)->signDocument($doc);
            $this->fail('Debió lanzar DocumentSigningException');
        } catch (DocumentSigningException $e) {
            $this->assertStringContainsString('conformidad', $e->getMessage());
        }

        $this->assertSame('%PDF-valido-anterior', Storage::disk('documents')->get('doc.pdf'));
        $this->assertFalse($doc->fresh()->digital_signature['includes_conformity']);
        $this->assertSame([], Storage::disk('documents')->files('.signing-tmp'));
    }

    public function test_backfill_pades_sin_base_llama_a_extract_base_y_luego_firma(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $doc->update(['digital_signature' => [
            'method' => 'pades_pyhanko', 'certificate_source' => 'global', 'certificate_ruc' => '20603839961',
            'includes_conformity' => false, 'first_signed_at' => '2026-10-01T10:00:00-05:00', 'resign_count' => 0,
        ]]);
        $doc = $this->workerSigned($doc->fresh());
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $extract = $this->lastRequest('/extract-base');
        $this->assertNotNull($extract);
        $this->assertSame(Storage::disk('documents')->path('doc.pdf'), $extract['input_path']);
        $this->assertSame(Storage::disk('documents')->path('.originals/doc.pdfa.pdf'), $extract['output_path']);

        $sign = $this->lastRequest('/sign');
        $this->assertSame($extract['output_path'], $sign['input_path']);
        $this->assertTrue($sign['skip_normalize']);
        $this->assertArrayHasKey('conformity', $sign->data());

        $fresh = $doc->fresh();
        $this->assertSame('.originals/doc.pdfa.pdf', $fresh->original_file_path);
        $this->assertTrue($fresh->digital_signature['includes_conformity']);
    }

    public function test_original_has_conformity_no_envia_conformity(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned(attrs: ['original_has_conformity' => true]));
        $this->fakeSigner();

        // includes_conformity=false coincide con (isSigned && !has) = false: ya está al día.
        $this->assertFalse(app(DocumentSigningService::class)->needsSigning($doc));
        $this->expectException(DocumentSigningException::class);
        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_primera_firma_con_fpdi_previo_marca_original_has_conformity(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $doc = $this->workerSigned($doc, ['pdf_mark' => 'fpdi']);
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $this->assertArrayNotHasKey('conformity', $this->lastRequest('/sign')->data());
        $fresh = $doc->fresh();
        $this->assertTrue($fresh->original_has_conformity);
        $this->assertFalse($fresh->digital_signature['includes_conformity']);
    }

    public function test_heuristica_por_file_size_sin_pdf_mark(): void
    {
        $this->globalCert();
        // file_size distinto al real del archivo => FPDI escribió (legacy sin pdf_mark).
        $doc = $this->document(['file_size' => 999999]);
        $doc = $this->workerSigned($doc, ['pdf_mark' => null]);
        $doc->update(['signature' => array_diff_key($doc->signature, ['pdf_mark' => 1])]);
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc->fresh());

        $this->assertArrayNotHasKey('conformity', $this->lastRequest('/sign')->data());
        $this->assertTrue($doc->fresh()->original_has_conformity);
    }

    public function test_primera_firma_con_trabajador_firmado_via_pades_envia_conformity_con_la_base_cruda(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->document());
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $sign = $this->lastRequest('/sign');
        $this->assertArrayHasKey('base_output_path', $sign->data());
        $this->assertArrayHasKey('conformity', $sign->data());
        $fresh = $doc->fresh();
        $this->assertFalse($fresh->original_has_conformity);
        $this->assertTrue($fresh->digital_signature['includes_conformity']);
    }

    public function test_resfirma_con_signature_enabled_apagado_se_permite(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned());
        SignatureSettings::current()->update(['signature_enabled' => false]);

        app(DocumentSigningService::class)->assertEligible($doc);
        $this->addToAssertionCount(1);
    }

    public function test_primera_firma_con_signature_enabled_apagado_no_se_permite(): void
    {
        $this->globalCert();
        $doc = $this->document();
        SignatureSettings::current()->update(['signature_enabled' => false]);

        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessage('no está activada');
        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_resfirma_con_certificado_global_cuando_firmo_la_empresa_falla(): void
    {
        $this->globalCert();
        // Firmó con el certificado de la empresa; luego se borra y solo queda el global.
        $doc = $this->workerSigned($this->digitallySigned(['certificate_source' => 'tenant', 'certificate_ruc' => '20100000001']));

        try {
            app(DocumentSigningService::class)->assertEligible($doc);
            $this->fail('Debió lanzar DocumentSigningException');
        } catch (DocumentSigningException $e) {
            $this->assertSame('El certificado actual no corresponde al firmante original.', $e->getMessage());
        }
    }

    public function test_resfirma_con_otro_ruc_falla(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned(['certificate_ruc' => '20999999999']));

        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessage('no corresponde al firmante original');
        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_applies_to_con_certificado_no_disponible_devuelve_false_sin_excepcion(): void
    {
        $this->globalCert();
        $record = $this->tenantCert();
        Storage::disk('certificates')->delete($record->certificate_path);
        $doc = $this->document(['digital_signature_status' => null]);

        $this->assertFalse(app(DocumentSigningService::class)->appliesTo($doc));
    }

    public function test_applies_to_casos(): void
    {
        $svc = app(DocumentSigningService::class);

        // Sin firma activada ni nada: no aplica.
        $this->assertFalse($svc->appliesTo($this->document(['digital_signature_status' => null])));

        $this->globalCert();
        $this->assertTrue($svc->appliesTo($this->document(['digital_signature_status' => null])));
        // Huérfano / sin requires_signature: no aplica.
        $this->assertFalse($svc->appliesTo($this->document(['digital_signature_status' => null, 'requires_signature' => false])));
        // (b) pending aplica aunque se haya apagado la firma.
        SignatureSettings::current()->update(['signature_enabled' => false]);
        $this->assertTrue($svc->appliesTo($this->document(['digital_signature_status' => 'pending'])));
        $this->assertFalse($svc->appliesTo($this->document(['digital_signature_status' => null])));
    }

    public function test_needs_signing_false_hace_que_assert_eligible_lance(): void
    {
        $this->globalCert();
        $doc = $this->digitallySigned(); // no firmado por el trabajador: includes=false === false

        $this->assertFalse(app(DocumentSigningService::class)->needsSigning($doc));
        $this->expectException(DocumentSigningException::class);
        $this->expectExceptionMessage('ya está al día');
        app(DocumentSigningService::class)->assertEligible($doc);
    }

    public function test_pades_permitido_con_el_trabajador_firmado(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->document());

        app(DocumentSigningService::class)->assertEligible($doc);
        $this->addToAssertionCount(1);
    }

    public function test_resultado_descartado_si_el_documento_fue_reemplazado_durante_la_firma(): void
    {
        $this->globalCert();
        $doc = $this->document();
        Http::fake(['*/sign' => function ($request) use ($doc) {
            file_put_contents($request['output_path'], '%PDF-signed');
            @mkdir(dirname($request['base_output_path']), 0777, true);
            file_put_contents($request['base_output_path'], '%PDF-base');
            // Reemplazo concurrente (ProcessDocumentChunk): otra version y estado limpio.
            Document::whereKey($doc->id)->update(['version' => 2, 'digital_signature_status' => null]);

            return Http::response(['success' => true, 'signature' => ['signer_subject' => 'X']]);
        }]);

        $result = app(DocumentSigningService::class)->signDocument($doc);

        $this->assertSame([], $result);
        $fresh = $doc->fresh();
        $this->assertNull($fresh->digital_signature);
        $this->assertSame('%PDF-1.4 dummy', Storage::disk('documents')->get('doc.pdf'));
        $this->assertSame([], Storage::disk('documents')->files('.signing-tmp'));
    }

    public function test_si_el_trabajador_firma_durante_el_job_queda_pending_y_se_reencola(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->globalCert();
        $doc = $this->document();
        Http::fake(['*/sign' => function ($request) use ($doc) {
            file_put_contents($request['output_path'], '%PDF-signed');
            @mkdir(dirname($request['base_output_path']), 0777, true);
            file_put_contents($request['base_output_path'], '%PDF-base');
            // El trabajador da su conformidad mientras el sidecar trabaja.
            Document::whereKey($doc->id)->update([
                'status' => 'signed', 'signed_at' => now(),
                'signature' => json_encode(['user_name' => 'X', 'pdf_mark' => 'pades', 'timestamp' => now()->toIso8601String()]),
            ]);

            return Http::response(['success' => true, 'signature' => ['signer_subject' => 'X']]);
        }]);

        app(DocumentSigningService::class)->signDocument($doc);

        $fresh = $doc->fresh();
        $this->assertSame('pending', $fresh->digital_signature_status);
        $this->assertFalse($fresh->digital_signature['includes_conformity']);
        \Illuminate\Support\Facades\Queue::assertPushedOn('signing-priority', \App\Jobs\SignDocument::class);
    }

    public function test_errores_de_tsa_tienen_mensaje_especifico_y_no_tocan_el_archivo(): void
    {
        $this->globalCert();
        $doc = $this->workerSigned($this->digitallySigned());
        Http::fake(['*/sign' => Http::response(['success' => false, 'stage' => 'tsa', 'error' => 'timeout'], 500)]);

        try {
            app(DocumentSigningService::class)->signDocument($doc);
            $this->fail('Debió lanzar DocumentSigningException');
        } catch (DocumentSigningException $e) {
            $this->assertStringContainsString('Sello de tiempo no disponible: timeout', $e->getMessage());
        }

        $this->assertSame('%PDF-1.4 dummy', Storage::disk('documents')->get('doc.pdf'));
    }

    public function test_registra_auditoria_de_firma_digital_sin_secretos(): void
    {
        $this->globalCert();
        $doc = $this->document();
        $this->fakeSigner();

        app(DocumentSigningService::class)->signDocument($doc);

        $log = \App\Models\AuditLog::where('action', \App\Models\AuditLog::ACTION_DOCUMENT_DIGITALLY_SIGNED)->first();
        $this->assertNotNull($log);
        $this->assertSame($doc->id, $log->entity_id);
        $this->assertSame('global', $log->metadata['certificate_source']);
        $this->assertFalse($log->metadata['includes_conformity']);
        $this->assertStringNotContainsString('secret', json_encode($log->metadata));
    }

    public function test_mark_failed_if_owned_no_pisa_un_signed_posterior(): void
    {
        $doc = $this->digitallySigned();
        $doc->update(['digital_signature_status' => 'signed']);

        app(DocumentSigningService::class)->markFailedIfOwned($doc->fresh(), 'boom');

        $this->assertSame('signed', $doc->fresh()->digital_signature_status);
    }

    public function test_mark_failed_if_owned_marca_failed_con_error_si_hace_falta(): void
    {
        $doc = $this->workerSigned($this->digitallySigned());

        app(DocumentSigningService::class)->markFailedIfOwned($doc, 'Sello de tiempo no disponible: x');

        $fresh = $doc->fresh();
        $this->assertSame('failed', $fresh->digital_signature_status);
        $this->assertSame('Sello de tiempo no disponible: x', $fresh->digital_signature_error);
        $this->assertNotNull($fresh->digital_signature); // se sigue sirviendo la última firma válida
    }
}
