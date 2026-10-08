<?php

namespace App\Services\Signature;

use Carbon\CarbonInterface;

/**
 * Certificado efectivo con el que se firmará un documento: el propio de la
 * empresa ('tenant') o, si no tiene, el global de la plataforma ('global').
 * USO INTERNO: contiene la password en claro, nunca debe serializarse a la API.
 */
final class EffectiveCertificate
{
    public function __construct(
        public readonly string $source,          // 'tenant' | 'global'
        public readonly string $absolutePath,
        public readonly string $password,
        public readonly ?string $subject,
        public readonly ?string $ruc,
        public readonly ?string $organization,
        public readonly ?CarbonInterface $expiresAt,
        // TSA propia de la empresa (null = usar la global); en 'global', la de SignatureSettings.
        public readonly ?string $tsaUrl = null,
    ) {
    }
}
