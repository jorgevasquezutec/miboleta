<?php

namespace Tests\Unit\Services;

use App\Services\Signature\CertificateInspector;
use InvalidArgumentException;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

class CertificateInspectorTest extends TestCase
{
    use MakesTestCertificates;

    public function test_extract_ruc_desde_serial_number_y_no_el_dni(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['serialNumber' => 'RUC:20603839961 DNI:09926071']));
    }

    public function test_extract_ruc_desde_cn_solo(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['CN' => 'BASILIO VENTURA WILLIAM RUC:20603839961']));
    }

    public function test_extract_ruc_pegado_al_dni(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['serialNumber' => 'RUC:20603839961DNI09926071']));
    }

    public function test_extract_ruc_con_variantes_de_texto(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['CN' => 'X RUC N° 20603839961']));
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['CN' => 'X RUC Nro. 20603839961']));
    }

    public function test_extract_ruc_desde_organization_identifier(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['organizationIdentifier' => 'VATPE-20603839961']));
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['2.5.4.97' => 'NTRPE-20603839961']));
    }

    public function test_extract_ruc_fallback_serial_number(): void
    {
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['serialNumber' => '20603839961']));
        $this->assertSame('20603839961', CertificateInspector::extractRuc(['serialNumber' => 'PE20603839961']));
    }

    public function test_extract_ruc_desde_ou_array(): void
    {
        $this->assertSame('20100000001', CertificateInspector::extractRuc(['OU' => ['Otra', 'RUC: 20100000001']]));
    }

    public function test_extract_ruc_sin_ruc_da_null(): void
    {
        $this->assertNull(CertificateInspector::extractRuc(['CN' => 'JUAN PEREZ', 'O' => 'ACME']));
    }

    public function test_from_parsed_o_como_array_toma_el_primero(): void
    {
        $info = CertificateInspector::fromParsed(['subject' => ['O' => ['PRIMERA SAC', 'SEGUNDA SAC'], 'CN' => 'X']]);
        $this->assertSame('PRIMERA SAC', $info->organization);
    }

    public function test_from_parsed_fallback_kv_con_ou_array_sin_error(): void
    {
        $info = CertificateInspector::fromParsed(['subject' => ['OU' => ['UnoOU', 'DosOU'], 'C' => 'PE']]);

        $this->assertStringContainsString('UnoOU', $info->subject);
        $this->assertStringContainsString('DosOU', $info->subject);
        $this->assertStringContainsString('C=PE', $info->subject);
    }

    public function test_from_parsed_trunca_a_255(): void
    {
        $largo = str_repeat('A', 400);
        $info = CertificateInspector::fromParsed(['subject' => ['CN' => $largo, 'O' => $largo]]);

        $this->assertSame(255, mb_strlen($info->subject));
        $this->assertSame(255, mb_strlen($info->organization));
    }

    public function test_from_parsed_expires_at_en_zona_de_la_app(): void
    {
        $ts = 1853513460;
        $info = CertificateInspector::fromParsed(['subject' => ['CN' => 'X'], 'validTo_time_t' => $ts]);

        $this->assertSame($ts, $info->expiresAt->getTimestamp());
        $this->assertSame(config('app.timezone'), $info->expiresAt->getTimezone()->getName());
    }

    public function test_inspect_con_password_incorrecta_lanza(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CertificateInspector())->inspect($this->makePfx($this->defaultDn(), 'secret'), 'otra');
    }

    public function test_inspect_devuelve_los_metadatos(): void
    {
        $info = (new CertificateInspector())->inspect($this->makePfx($this->defaultDn(), 'secret', 10), 'secret');

        $this->assertSame('JUAN PEREZ RUC:20603839961', $info->subject);
        $this->assertSame('20603839961', $info->ruc);
        $this->assertSame('OVERHEAD MEN S.A.C.', $info->organization);
        $this->assertNotNull($info->expiresAt);
        $this->assertTrue($info->expiresAt->isFuture());
    }
}
