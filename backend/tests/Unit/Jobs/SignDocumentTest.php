<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SignDocument;
use App\Models\Document;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentSigningService;
use App\Services\PdfWatermarkService;
use App\Services\SignatureCertificateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

class SignDocumentTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('certificates');
        Storage::fake('documents');
        config(['services.signer.base_url' => 'http://signer.test']);

        $this->tenant = Tenant::factory()->create(['ruc' => '20603839961']);
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

    private function digital(array $extra = []): array
    {
        return $extra + [
            'method' => 'pades_pyhanko', 'certificate_source' => 'global', 'certificate_ruc' => '20603839961',
            'includes_conformity' => false, 'first_signed_at' => '2026-10-06T10:00:00-05:00', 'resign_count' => 0,
        ];
    }

    private function runJob(Document $doc): void
    {
        (new SignDocument($doc->id))->handle(app(DocumentSigningService::class));
    }

    public function test_configuracion_del_job(): void
    {
        $job = new SignDocument(7);

        $this->assertSame(0, $job->tries);
        $this->assertSame(3, $job->maxExceptions);
        $this->assertSame([30, 120, 300], $job->backoff);
        $this->assertSame(180, $job->timeout);
        $this->assertEqualsWithDelta(now()->addMinutes(30)->timestamp, $job->retryUntil()->getTimestamp(), 5);

        $middleware = $job->middleware();
        $this->assertCount(1, $middleware);
        $this->assertInstanceOf(WithoutOverlapping::class, $middleware[0]);
        $this->assertSame('document-signing:7', $middleware[0]->key);
        $this->assertSame(30, $middleware[0]->releaseAfter);
        $this->assertSame(300, $middleware[0]->expiresAfter);
    }

    public function test_usa_la_conexion_redis_signing_y_la_cola_de_prioridad(): void
    {
        config(['queue.default' => 'redis']);
        Queue::fake();

        SignDocument::dispatch(1)->onQueue('signing-priority');

        Queue::assertPushed(SignDocument::class, fn ($job) => $job->connection === 'redis-signing' && $job->queue === 'signing-priority');
        $this->assertSame(300, config('queue.connections.redis-signing.retry_after'));
        $this->assertSame('redis-signing', config('horizon.defaults.signing-supervisor.connection'));
        $this->assertSame(['signing-priority', 'signing'], config('horizon.defaults.signing-supervisor.queue'));
    }

    public function test_idempotencia_si_no_necesita_firma_no_llama_al_sidecar_y_deja_signed(): void
    {
        Http::fake();
        $doc = $this->document(['digital_signature' => $this->digital(), 'digital_signature_status' => 'pending']);

        $this->runJob($doc);

        Http::assertNothingSent();
        $fresh = $doc->fresh();
        $this->assertSame('signed', $fresh->digital_signature_status);
        $this->assertSame('pending', $fresh->status); // nunca el status del trabajador
    }

    public function test_si_ya_no_esta_pending_no_hace_nada(): void
    {
        Http::fake();
        $doc = $this->document(['digital_signature_status' => null]);

        $this->runJob($doc);

        Http::assertNothingSent();
        $this->assertNull($doc->fresh()->digital_signature_status);
    }

    public function test_el_trabajador_firma_durante_el_job_se_reencola_y_queda_pending(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $doc = $this->document();
        Http::fake(['*/sign' => function ($request) use ($doc) {
            file_put_contents($request['output_path'], '%PDF-signed');
            @mkdir(dirname($request['base_output_path']), 0777, true);
            file_put_contents($request['base_output_path'], '%PDF-base');
            Document::whereKey($doc->id)->update([
                'status' => 'signed', 'signed_at' => now(),
                'signature' => json_encode(['user_name' => 'Ana', 'pdf_mark' => 'pades', 'timestamp' => now()->toIso8601String()]),
            ]);

            return Http::response(['success' => true, 'signature' => ['signer_subject' => 'X']]);
        }]);

        $this->runJob($doc);

        $fresh = $doc->fresh();
        $this->assertSame('pending', $fresh->digital_signature_status);
        $this->assertFalse($fresh->digital_signature['includes_conformity']);
        Queue::assertPushedOn('signing-priority', SignDocument::class);
    }

    public function test_reemplazo_durante_el_job_descarta_el_temporal_y_no_toca_el_archivo(): void
    {
        $this->enableGlobalSignature();
        $doc = $this->document();
        Http::fake(['*/sign' => function ($request) use ($doc) {
            file_put_contents($request['output_path'], '%PDF-signed');
            @mkdir(dirname($request['base_output_path']), 0777, true);
            file_put_contents($request['base_output_path'], '%PDF-base');
            Storage::disk('documents')->put('doc.pdf', '%PDF-nuevo-archivo');
            Document::whereKey($doc->id)->update(['version' => 2, 'digital_signature_status' => null]);

            return Http::response(['success' => true, 'signature' => ['signer_subject' => 'X']]);
        }]);

        $this->runJob($doc);

        $fresh = $doc->fresh();
        $this->assertNull($fresh->digital_signature);
        $this->assertNull($fresh->digital_signature_status);
        $this->assertSame('%PDF-nuevo-archivo', Storage::disk('documents')->get('doc.pdf'));
        $this->assertSame([], Storage::disk('documents')->files('.signing-tmp'));
    }

    public function test_failed_no_pisa_un_signed_posterior(): void
    {
        $doc = $this->document(['digital_signature' => $this->digital(), 'digital_signature_status' => 'signed']);

        (new SignDocument($doc->id))->failed(new \RuntimeException('boom'));

        $this->assertSame('signed', $doc->fresh()->digital_signature_status);
    }

    public function test_failed_con_pades_previo_marca_failed_y_conserva_la_firma_valida(): void
    {
        $doc = $this->document([
            'digital_signature' => $this->digital(),
            'status' => 'signed', 'signed_at' => now(),
            'signature' => ['user_name' => 'Ana', 'pdf_mark' => 'pades', 'timestamp' => now()->toIso8601String()],
        ]);
        $mock = Mockery::mock(PdfWatermarkService::class)->makePartial();
        $mock->shouldNotReceive('addSignatureWatermark');
        $this->app->instance(PdfWatermarkService::class, $mock);

        (new SignDocument($doc->id))->failed(new \RuntimeException('Sello de tiempo no disponible: x'));

        $fresh = $doc->fresh();
        $this->assertSame('failed', $fresh->digital_signature_status);
        $this->assertSame('Sello de tiempo no disponible: x', $fresh->digital_signature_error);
        $this->assertNotNull($fresh->digital_signature);
    }

    public function test_failed_sin_pades_y_con_el_trabajador_firmado_usa_el_respaldo_fpdi(): void
    {
        $doc = $this->document([
            'status' => 'signed', 'signed_at' => now(),
            'signature' => ['user_name' => 'Ana', 'pdf_mark' => 'pades', 'timestamp' => now()->toIso8601String()],
        ]);
        $mock = Mockery::mock(PdfWatermarkService::class)->makePartial();
        $mock->shouldReceive('addSignatureWatermark')->once()->andReturn(true);
        $this->app->instance(PdfWatermarkService::class, $mock);

        (new SignDocument($doc->id))->failed(new \RuntimeException('sidecar caído'));

        $fresh = $doc->fresh();
        $this->assertSame('failed', $fresh->digital_signature_status);
        $this->assertSame('fpdi', $fresh->signature['pdf_mark']);
        $this->assertSame('sidecar caído', $fresh->digital_signature_error);
        $this->assertNull($fresh->digital_signature);

        // Un segundo failed() no estampa el nombre otra vez.
        (new SignDocument($doc->id))->failed(new \RuntimeException('otra vez'));
    }

    public function test_failed_sin_pades_firmado_antes_sin_pdf_mark_y_con_otro_tamano_no_estampa_de_nuevo(): void
    {
        // Firmado con FPDI antes de pdf_mark: file_size (zip) != tamaño real.
        $doc = $this->document([
            'status' => 'signed', 'signed_at' => now(),
            'file_size' => 999999,
            'signature' => ['user_name' => 'Ana', 'timestamp' => now()->toIso8601String()],
        ]);
        $mock = Mockery::mock(PdfWatermarkService::class)->makePartial();
        $mock->shouldNotReceive('addSignatureWatermark');
        $this->app->instance(PdfWatermarkService::class, $mock);

        (new SignDocument($doc->id))->failed(new \RuntimeException('sidecar caído'));

        $fresh = $doc->fresh();
        $this->assertSame('failed', $fresh->digital_signature_status);
        $this->assertSame('fpdi', $fresh->signature['pdf_mark']);
        $this->assertTrue($fresh->original_has_conformity);
    }

    public function test_condicion_permanente_aplica_la_regla_de_fallo_sin_reintentar(): void
    {
        // Primera firma con la firma de plataforma apagada: no elegible (permanente).
        Http::fake();
        $doc = $this->document();

        $this->runJob($doc); // no lanza

        Http::assertNothingSent();
        $fresh = $doc->fresh();
        $this->assertSame('failed', $fresh->digital_signature_status);
        $this->assertStringContainsString('no está activada', $fresh->digital_signature_error);
    }

    public function test_error_del_sidecar_se_relanza_para_que_reintente_el_worker(): void
    {
        $this->enableGlobalSignature();
        $doc = $this->document();
        Http::fake(['*/sign' => Http::response(['success' => false, 'error' => 'gs falló'], 500)]);

        $this->expectException(\App\Exceptions\DocumentSigningException::class);
        try {
            $this->runJob($doc);
        } finally {
            // Mientras reintenta sigue 'pending' y el archivo no cambió.
            $this->assertSame('pending', $doc->fresh()->digital_signature_status);
            $this->assertSame('%PDF-1.4 dummy', Storage::disk('documents')->get('doc.pdf'));
        }
    }
}
