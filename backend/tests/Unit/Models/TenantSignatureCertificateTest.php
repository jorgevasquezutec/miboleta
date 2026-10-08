<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\TenantSignatureCertificate;
use App\Services\Signature\CertificateInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

class TenantSignatureCertificateTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function make(array $attrs = []): TenantSignatureCertificate
    {
        $tenant = Tenant::factory()->create(['ruc' => '20603839961']);

        return TenantSignatureCertificate::create(array_merge([
            'tenant_id' => $tenant->id,
            'certificate_path' => 'x.pfx',
            'certificate_password' => 'secret',
            'certificate_ruc' => '20603839961',
            'certificate_expires_at' => now()->addYear(),
        ], $attrs));
    }

    public function test_ruc_matches_normaliza_y_devuelve_null_si_falta_un_lado(): void
    {
        $c = $this->make();

        $this->assertTrue($c->rucMatches('206-0383-9961'));
        $this->assertFalse($c->rucMatches('20100000001'));
        $this->assertNull($c->rucMatches(null));
        $this->assertNull($c->rucMatches(''));

        $c->certificate_ruc = null;
        $this->assertNull($c->rucMatches('20603839961'));
    }

    public function test_warnings_vacio_si_todo_esta_bien(): void
    {
        $this->assertSame([], $this->make()->warnings('20603839961'));
    }

    public function test_warning_ruc_mismatch_y_ruc_not_found(): void
    {
        $c = $this->make();
        $this->assertSame('ruc_mismatch', $c->warnings('20100000001')[0]['code']);

        $c->certificate_ruc = null;
        $this->assertSame('ruc_not_found', $c->warnings('20100000001')[0]['code']);
    }

    public function test_warning_expired(): void
    {
        $info = (new CertificateInspector())->inspect($this->makePfx($this->defaultDn(), 'secret', 1), 'secret');
        $c = $this->make(['certificate_expires_at' => $info->expiresAt]);

        Carbon::setTestNow(now()->addDays(2));

        $codes = array_column($c->fresh()->warnings('20603839961'), 'code');
        $this->assertSame(['expired'], $codes);
    }

    public function test_warning_expiring_soon_con_dias_enteros(): void
    {
        $info = (new CertificateInspector())->inspect($this->makePfx($this->defaultDn(), 'secret', 10), 'secret');
        $c = $this->make(['certificate_expires_at' => $info->expiresAt]);

        $w = $c->fresh()->warnings('20603839961');

        $this->assertSame('expiring_soon', $w[0]['code']);
        $this->assertMatchesRegularExpression('/\(en \d+ días\)/', $w[0]['message']);
    }

    public function test_password_se_guarda_cifrada_y_no_se_serializa(): void
    {
        $c = $this->make();

        $this->assertNotSame('secret', DB::table('tenant_signature_certificates')->value('certificate_password'));
        $this->assertSame('secret', $c->fresh()->certificate_password);
        $this->assertArrayNotHasKey('certificate_password', $c->toArray());
        $this->assertArrayNotHasKey('certificate_path', $c->toArray());
    }

    public function test_round_trip_de_la_fecha_de_vencimiento(): void
    {
        $ts = 1853513460; // 2028-09-21 15:51:00 UTC
        $info = CertificateInspector::fromParsed(['subject' => ['CN' => 'X'], 'validTo_time_t' => $ts]);
        $c = $this->make(['certificate_expires_at' => $info->expiresAt]);

        $fresh = $c->fresh();
        $this->assertSame($ts, $fresh->certificate_expires_at->getTimestamp());
        $this->assertSame(
            gmdate('Y-m-d\TH:i:s', $ts) . '.000000Z',
            $fresh->certificate_expires_at->toJSON()
        );
    }
}
