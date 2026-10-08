<?php

namespace App\Services\Signature;

use Carbon\CarbonImmutable;

/**
 * Metadatos legibles extraídos de un certificado X.509 (.pfx/.p12).
 */
final class CertificateInfo
{
    public function __construct(
        public readonly ?string $subject,
        public readonly ?string $ruc,
        public readonly ?string $organization,
        public readonly ?CarbonImmutable $expiresAt,
    ) {
    }

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }
}
