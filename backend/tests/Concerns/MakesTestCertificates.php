<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;

/**
 * Genera .pfx de prueba en memoria con openssl (sin fixtures binarios).
 */
trait MakesTestCertificates
{
    /**
     * @param  array  $dn  Ej: ['countryName'=>'PE','organizationName'=>'X','commonName'=>'Y RUC:20603839961','serialNumber'=>'RUC:20603839961 DNI:09926071']
     * @param  int  $days  Debe ser >= 1 (openssl_csr_sign rechaza negativos)
     */
    protected function makePfx(array $dn, string $password = 'secret', int $days = 365): UploadedFile
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]);
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256']);
        openssl_pkcs12_export($x509, $out, $key, $password);

        return UploadedFile::fake()->createWithContent('cert.pfx', $out);
    }

    protected function defaultDn(string $ruc = '20603839961'): array
    {
        return [
            'countryName' => 'PE',
            'organizationName' => 'OVERHEAD MEN S.A.C.',
            'commonName' => "JUAN PEREZ RUC:{$ruc}",
            'serialNumber' => "RUC:{$ruc} DNI:09926071",
        ];
    }
}
