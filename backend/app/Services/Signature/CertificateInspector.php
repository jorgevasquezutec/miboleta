<?php

namespace App\Services\Signature;

use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Abre un .pfx/.p12 con su password y extrae subject, RUC, razón social y
 * vencimiento. Es best-effort si openssl no está disponible; si lo está y la
 * password es incorrecta, lanza InvalidArgumentException (feedback temprano).
 */
class CertificateInspector
{
    /**
     * @throws InvalidArgumentException Si no se puede abrir con la password
     */
    public function inspect(UploadedFile $file, string $password): CertificateInfo
    {
        if (!function_exists('openssl_pkcs12_read')) {
            Log::warning('[CertificateInspector] openssl_pkcs12_read no disponible; se omite validación temprana del certificado');
            return CertificateInfo::empty();
        }

        try {
            $raw = file_get_contents($file->getRealPath());
        } catch (\Throwable $e) {
            $raw = false;
        }

        if ($raw === false || $raw === '') {
            return CertificateInfo::empty();
        }

        $certs = [];
        // @: openssl_pkcs12_read emite un warning nativo en vez de excepción.
        $ok = @openssl_pkcs12_read($raw, $certs, $password);

        if (!$ok) {
            throw new InvalidArgumentException(
                'No se pudo abrir el certificado con la contraseña proporcionada. Verifique el archivo .pfx/.p12 y la contraseña.'
            );
        }

        try {
            $parsed = openssl_x509_parse($certs['cert'] ?? '');

            return self::fromParsed($parsed ?: []);
        } catch (\Throwable $e) {
            Log::warning('[CertificateInspector] No se pudo extraer la información del certificado', [
                'error' => $e->getMessage(),
            ]);
            return CertificateInfo::empty();
        }
    }

    /**
     * Pura y testeable: recibe el resultado de openssl_x509_parse().
     */
    public static function fromParsed(array $parsed): CertificateInfo
    {
        $subject = $parsed['subject'] ?? [];
        if (!is_array($subject)) {
            $subject = [];
        }

        $label = self::first($subject['CN'] ?? null);

        if ($label === null && $subject) {
            // Aplanado explícito: OU (y otros) pueden venir como array.
            $parts = [];
            foreach ($subject as $key => $value) {
                foreach (self::flatten($value) as $item) {
                    $parts[] = "{$key}={$item}";
                }
            }
            $label = implode(', ', $parts) ?: null;
        }

        $organization = self::first($subject['O'] ?? null);
        $organization = $organization !== null ? trim($organization) : null;

        $expiresAt = isset($parsed['validTo_time_t'])
            // NO createFromTimestampUTC: el cast datetime guarda la hora de
            // pared sin convertir y quedaría desfasada 5 horas.
            ? CarbonImmutable::createFromTimestamp((int) $parsed['validTo_time_t'], config('app.timezone'))
            : null;

        return new CertificateInfo(
            $label !== null ? Str::limit($label, 255, '') : null,
            self::extractRuc($subject),
            $organization !== null && $organization !== '' ? Str::limit($organization, 255, '') : null,
            $expiresAt,
        );
    }

    /**
     * Extrae el RUC (11 dígitos) del subject; prioriza el patrón "RUC..." para
     * no confundirlo con el DNI.
     */
    public static function extractRuc(array $subject): ?string
    {
        $orgId = self::first($subject['organizationIdentifier'] ?? ($subject['2.5.4.97'] ?? null));

        $candidates = [];
        $priority = ['serialNumber', 'organizationIdentifier', '2.5.4.97', 'CN', 'OU', 'title'];
        foreach ($priority as $key) {
            foreach (self::flatten($subject[$key] ?? null) as $v) {
                $candidates[] = $v;
            }
        }
        foreach ($subject as $key => $value) {
            if (in_array($key, $priority, true)) {
                continue;
            }
            foreach (self::flatten($value) as $v) {
                $candidates[] = $v;
            }
        }

        foreach ($candidates as $candidate) {
            if (preg_match('/RUC\D{0,8}?(?<!\d)(\d{11})(?!\d)/iu', $candidate, $m)) {
                return $m[1];
            }
        }

        if ($orgId !== null && preg_match('/^[A-Z]{3}PE-?(\d{11})$/i', trim($orgId), $m)) {
            return $m[1];
        }

        $serial = self::first($subject['serialNumber'] ?? null);
        if ($serial !== null && preg_match('/^\D{0,6}((?:10|15|16|17|20)\d{9})$/', trim($serial), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function flatten(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $v) {
                foreach (self::flatten($v) as $item) {
                    $out[] = $item;
                }
            }
            return $out;
        }

        return is_string($value) && $value !== '' ? [$value] : [];
    }

    private static function first(mixed $value): ?string
    {
        $flat = self::flatten($value);

        return $flat[0] ?? null;
    }
}
