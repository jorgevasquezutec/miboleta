<?php

namespace Tests\Unit\Services;

use App\Models\SignatureSettings;
use App\Models\User;
use App\Services\Signature\CertificateInspector;
use App\Services\SignatureCertificateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

/**
 * Certificado GLOBAL: metadatos nuevos y reglas de activación.
 */
class SignatureCertificateServiceTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private SignatureCertificateService $service;
    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('certificates');

        $this->service = app(SignatureCertificateService::class);
        $this->root = User::factory()->root()->create(['status' => 'active']);
    }

    public function test_store_certificate_guarda_ruc_organizacion_y_vencimiento(): void
    {
        $file = $this->makePfx($this->defaultDn('20603839961'), 'secret', 30);
        $expected = (new CertificateInspector())->inspect($file, 'secret')->expiresAt;

        $settings = $this->service->storeCertificate($this->root, $file, 'secret');

        $this->assertSame('20603839961', $settings->certificate_ruc);
        $this->assertSame('OVERHEAD MEN S.A.C.', $settings->certificate_organization);
        $this->assertSame($expected->getTimestamp(), $settings->certificate_expires_at->getTimestamp());
    }

    public function test_delete_certificate_limpia_los_metadatos(): void
    {
        $this->service->storeCertificate($this->root, $this->makePfx($this->defaultDn()), 'secret');

        $settings = $this->service->deleteCertificate($this->root)->fresh();

        $this->assertNull($settings->certificate_ruc);
        $this->assertNull($settings->certificate_organization);
        $this->assertNull($settings->certificate_expires_at);
        $this->assertFalse($settings->signature_enabled);
    }

    public function test_enable_signature_sigue_exigiendo_certificado_global(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->enableSignature($this->root, true);
    }

    public function test_enable_signature_funciona_con_certificado_global(): void
    {
        $this->service->storeCertificate($this->root, $this->makePfx($this->defaultDn()), 'secret');

        $this->assertTrue($this->service->enableSignature($this->root, true)->signature_enabled);
        $this->assertTrue(SignatureSettings::current()->signature_enabled);
    }
}
