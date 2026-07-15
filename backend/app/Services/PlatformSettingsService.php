<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\PlatformSettings;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona la configuración GENERAL de la plataforma: IP pública del servidor
 * (ítem 23) y el servidor SMTP global (mailer por defecto administrable). Solo
 * root puede leer/editar, mismo patrón de enforcement que
 * SignatureCertificateService::ensureRoot().
 */
class PlatformSettingsService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * @throws UnauthorizedAccessException
     */
    public function getSettings(User $user): PlatformSettings
    {
        $this->ensureRoot($user);

        return PlatformSettings::current();
    }

    /**
     * Snapshot auditable de la configuración de plataforma: nunca incluye la
     * contraseña SMTP (solo el booleano has_mail_password).
     */
    protected function auditableSnapshot(PlatformSettings $settings): array
    {
        return [
            'public_ip' => $settings->public_ip,
            'mail_host' => $settings->mail_host,
            'mail_port' => $settings->mail_port,
            'mail_username' => $settings->mail_username,
            'mail_encryption' => $settings->mail_encryption,
            'mail_from_address' => $settings->mail_from_address,
            'mail_from_name' => $settings->mail_from_name,
            'has_mail_password' => !empty($settings->mail_password),
        ];
    }

    /**
     * Actualiza la configuración de plataforma con el array de datos ya
     * validado (UpdatePlatformSettingsRequest). La contraseña SMTP es
     * write-only: solo se reescribe si viene presente y no vacía, para no
     * borrarla al guardar el resto de campos (mismo patrón que
     * TenantService::updateTenant()).
     *
     * @param array<string, mixed> $data
     * @throws UnauthorizedAccessException
     */
    public function updateSettings(User $user, array $data): PlatformSettings
    {
        $this->ensureRoot($user);

        $settings = PlatformSettings::current();
        $oldSnapshot = $this->auditableSnapshot($settings);

        $settings->fill([
            'public_ip' => $data['public_ip'] ?? null,
            'mail_host' => $data['mail_host'] ?? null,
            'mail_port' => $data['mail_port'] ?? null,
            'mail_username' => $data['mail_username'] ?? null,
            'mail_encryption' => $data['mail_encryption'] ?? null,
            'mail_from_address' => $data['mail_from_address'] ?? null,
            'mail_from_name' => $data['mail_from_name'] ?? null,
            'updated_by' => $user->id,
        ]);

        // Password write-only: solo se actualiza si el usuario envió una nueva.
        if (!empty($data['mail_password'])) {
            $settings->mail_password = $data['mail_password'];
        }

        $settings->save();

        $fresh = $settings->fresh();

        $this->auditService->logPlatformSettingsUpdated(
            $oldSnapshot,
            $this->auditableSnapshot($fresh)
        );

        Log::info('[PlatformSettingsService] configuración de plataforma actualizada', [
            'user_id' => $user->id,
        ]);

        return $fresh;
    }

    protected function ensureRoot(User $user): void
    {
        if (!$user->isRoot()) {
            throw new UnauthorizedAccessException(
                'No autorizado. Solo el administrador de plataforma puede gestionar la configuración de la plataforma.'
            );
        }
    }
}
