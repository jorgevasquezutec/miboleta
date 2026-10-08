<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Certificado de firma digital PROPIO de una empresa (uno por empresa). Si la
 * empresa no tiene fila, se usa el certificado global (SignatureSettings).
 * La password se cifra con el cast "encrypted" y ni ella ni la ruta del
 * binario se serializan jamás.
 */
class TenantSignatureCertificate extends Model
{
    public const EXPIRING_SOON_DAYS = 30;

    protected $table = 'tenant_signature_certificates';

    protected $fillable = [
        'tenant_id',
        'certificate_path',
        'certificate_password',
        'certificate_subject',
        'certificate_ruc',
        'certificate_organization',
        'certificate_expires_at',
        'tsa_url',
        'uploaded_by',
        'uploaded_at',
    ];

    protected $hidden = [
        'certificate_password',
        'certificate_path',
    ];

    protected $casts = [
        'certificate_password' => 'encrypted',
        'certificate_expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class)->withTrashed();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * ¿El RUC del certificado coincide con el de la empresa? null si no se
     * puede determinar. Se calcula al leer (no se guarda) para que siga
     * correcto si root edita tenants.ruc después.
     */
    public function rucMatches(?string $tenantRuc): ?bool
    {
        $cert = preg_replace('/\D/', '', (string) $this->certificate_ruc);
        $tenant = preg_replace('/\D/', '', (string) $tenantRuc);

        if ($cert === '' || $tenant === '') {
            return null;
        }

        return $cert === $tenant;
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->certificate_expires_at) {
            return null;
        }

        return (int) ceil(now()->diffInDays($this->certificate_expires_at, false));
    }

    /**
     * @return list<array{code: string, message: string}>
     */
    public function warnings(?string $tenantRuc): array
    {
        $warnings = [];

        if ($this->certificate_ruc === null || $this->certificate_ruc === '') {
            $warnings[] = [
                'code' => 'ruc_not_found',
                'message' => 'No se pudo leer el RUC del certificado; verifique que corresponda a la empresa.',
            ];
        } elseif ($this->rucMatches($tenantRuc) === false) {
            $warnings[] = [
                'code' => 'ruc_mismatch',
                'message' => "El RUC del certificado ({$this->certificate_ruc}) no coincide con el RUC de la empresa ({$tenantRuc}).",
            ];
        }

        if ($this->certificate_expires_at) {
            $fecha = $this->certificate_expires_at->copy()->timezone('America/Lima')->format('d/m/Y');

            if ($this->certificate_expires_at->isPast()) {
                $warnings[] = ['code' => 'expired', 'message' => "El certificado venció el {$fecha}."];
            } else {
                $dias = $this->daysUntilExpiry();
                if ($dias !== null && $dias > 0 && $dias <= self::EXPIRING_SOON_DAYS) {
                    $warnings[] = [
                        'code' => 'expiring_soon',
                        'message' => "El certificado vence el {$fecha} (en {$dias} días).",
                    ];
                }
            }
        }

        return $warnings;
    }
}
