<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\PlatformSettings;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Gestiona la configuración GENERAL de la plataforma (ítem 23: IP pública
 * del servidor). Solo root puede leer/editar, mismo patrón de enforcement
 * que SignatureCertificateService::ensureRoot().
 */
class PlatformSettingsService
{
    /**
     * @throws UnauthorizedAccessException
     */
    public function getSettings(User $user): PlatformSettings
    {
        $this->ensureRoot($user);

        return PlatformSettings::current();
    }

    /**
     * @throws UnauthorizedAccessException
     */
    public function updateSettings(User $user, ?string $publicIp): PlatformSettings
    {
        $this->ensureRoot($user);

        $settings = PlatformSettings::current();
        $settings->fill([
            'public_ip' => $publicIp,
            'updated_by' => $user->id,
        ]);
        $settings->save();

        Log::info('[PlatformSettingsService] public_ip actualizada', [
            'user_id' => $user->id,
        ]);

        return $settings->fresh();
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
