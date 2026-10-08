<?php

namespace Tests\Unit\Services;

use App\Jobs\SignDocument;
use App\Models\Document;
use App\Models\DocumentSignatureCode;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PdfWatermarkService;
use App\Services\SignatureCertificateService;
use App\Services\SignatureService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

/**
 * Conformidad del trabajador (2FA por correo) frente a la firma digital
 * (PAdES) de la empresa: con PAdES NO se usa FPDI (no puede abrir PDFs firmados
 * y reescribirlos invalidaría la firma); el PDF se regenera en el job.
 */
class SignatureServiceTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private Tenant $tenant;
    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('certificates');
        Storage::fake('documents');
        Mail::fake();
        Queue::fake();

        $this->tenant = Tenant::factory()->create(['ruc' => '20603839961']);
        $this->worker = User::factory()->withTenantRole($this->tenant, 'client', true)
            ->create(['status' => 'active', 'signature_terms_accepted_at' => now()]);
    }

    private function enableGlobalSignature(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);
        app(SignatureCertificateService::class)->storeCertificate(
            $root, $this->makePfx($this->defaultDn('20603839961'), 'secret', 365), 'secret', 'https://tsa.example/ts'
        );
        SignatureSettings::current()->update(['signature_enabled' => true]);
    }

    private function document(array $attrs = []): Document
    {
        $doc = Document::factory()->create($attrs + [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->worker->id,
            'uploaded_by' => $this->worker->id,
            'file_path' => 'doc.pdf',
        ]);
        Storage::disk('documents')->put('doc.pdf', '%PDF-1.4 contenido-que-vio-el-trabajador');

        return $doc;
    }

    private function sign(Document $doc): array
    {
        $code = DocumentSignatureCode::createForDocument($doc, $this->worker)['code'];

        return app(SignatureService::class)->verifyAndSign($this->worker, $doc->id, $code, ['ip' => '127.0.0.1', 'user_agent' => 'phpunit']);
    }

    private function mockWatermark(?bool $result): \Mockery\MockInterface
    {
        $mock = Mockery::mock(PdfWatermarkService::class)->makePartial();
        if ($result === null) {
            $mock->shouldNotReceive('addSignatureWatermark');
        } else {
            $mock->shouldReceive('addSignatureWatermark')->once()->andReturn($result);
        }
        $this->app->instance(PdfWatermarkService::class, $mock);

        return $mock;
    }

    public function test_con_pades_previo_el_trabajador_firma_sin_fpdi_y_se_encola_la_refirma(): void
    {
        $this->enableGlobalSignature();
        $this->mockWatermark(null); // FPDI never()
        $doc = $this->document(['digital_signature' => [
            'method' => 'pades_pyhanko', 'certificate_source' => 'global', 'certificate_ruc' => '20603839961',
            'includes_conformity' => false, 'first_signed_at' => now()->toIso8601String(), 'resign_count' => 0,
        ], 'digital_signature_status' => 'signed', 'digitally_signed_at' => now()]);
        $hash = hash('sha256', '%PDF-1.4 contenido-que-vio-el-trabajador');

        $result = $this->sign($doc);

        $this->assertTrue($result['success']);
        $this->assertSame('pending', $result['digital_signature_status']);
        $fresh = $doc->fresh();
        $this->assertSame('signed', $fresh->status);
        $this->assertNotNull($fresh->signed_at);
        $this->assertSame('pades', $fresh->signature['pdf_mark']);
        $this->assertSame($hash, $fresh->signature['document_sha256']);
        $this->assertSame('pending', $fresh->digital_signature_status);
        Queue::assertPushedOn('signing-priority', SignDocument::class, fn ($job) => $job->documentId === $doc->id);
        // El PDF que vio el trabajador no se tocó.
        $this->assertSame('%PDF-1.4 contenido-que-vio-el-trabajador', Storage::disk('documents')->get('doc.pdf'));
    }

    public function test_sin_pades_previo_pero_aplicable_tambien_encola_y_no_usa_fpdi(): void
    {
        $this->enableGlobalSignature();
        $this->mockWatermark(null);
        $doc = $this->document();

        $result = $this->sign($doc);

        $this->assertTrue($result['success']);
        $fresh = $doc->fresh();
        $this->assertSame('pades', $fresh->signature['pdf_mark']);
        $this->assertSame('pending', $fresh->digital_signature_status);
        Queue::assertPushedOn('signing-priority', SignDocument::class);
    }

    public function test_signature_enabled_apagado_usa_fpdi_como_antes_sin_encolar(): void
    {
        $this->mockWatermark(true);
        $doc = $this->document();

        $result = $this->sign($doc);

        $this->assertTrue($result['success']);
        $fresh = $doc->fresh();
        $this->assertSame('signed', $fresh->status);
        $this->assertSame('fpdi', $fresh->signature['pdf_mark']);
        $this->assertSame(hash('sha256', '%PDF-1.4 contenido-que-vio-el-trabajador'), $fresh->signature['document_sha256']);
        $this->assertNull($fresh->digital_signature_status);
        $this->assertNull($result['digital_signature_status']);
        Queue::assertNothingPushed();
    }

    public function test_fpdi_que_falla_deja_pdf_mark_fpdi_failed_pero_firma(): void
    {
        $this->mockWatermark(false);
        $doc = $this->document();

        $result = $this->sign($doc);

        $this->assertTrue($result['success']);
        $this->assertSame('fpdi_failed', $doc->fresh()->signature['pdf_mark']);
        $this->assertSame('signed', $doc->fresh()->status);
    }

    public function test_con_digital_pending_no_se_usa_fpdi_aunque_appliesto_sea_falso(): void
    {
        // La firma de plataforma está apagada, pero el archivo ya es del pipeline PAdES.
        $this->mockWatermark(null);
        $doc = $this->document(['digital_signature_status' => 'pending']);

        $result = $this->sign($doc);

        $this->assertTrue($result['success']);
        $this->assertSame('pades', $doc->fresh()->signature['pdf_mark']);
        Queue::assertPushedOn('signing-priority', SignDocument::class);
    }

    public function test_un_segundo_verify_and_sign_responde_ya_fue_firmado(): void
    {
        $this->mockWatermark(true);
        $doc = $this->document();
        $this->sign($doc);

        $second = $this->sign($doc);

        $this->assertFalse($second['success']);
        $this->assertSame('Este documento ya fue firmado', $second['error']);
        $this->assertSame(400, $second['status_code']);
    }

    public function test_get_status_incluye_los_campos_de_firma_digital(): void
    {
        $this->enableGlobalSignature();
        $doc = $this->document(['digital_signature' => [
            'method' => 'pades_pyhanko', 'includes_conformity' => true,
        ], 'digital_signature_status' => 'signed', 'digitally_signed_at' => now()]);

        $status = app(SignatureService::class)->getStatus($this->worker, $doc->id);

        $this->assertSame('signed', $status['digital_signature_status']);
        $this->assertNotNull($status['digitally_signed_at']);
        $this->assertTrue($status['includes_conformity']);
        $this->assertFalse($status['is_signed']);
    }
}
