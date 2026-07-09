<?php

namespace App\Services;

use App\Mail\DataUpdateRequestMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function __construct(
        protected TenantMailerService $tenantMailerService
    ) {
    }

    /**
     * Get user profile data.
     *
     * @param User $user
     * @return array
     */
    public function getProfile(User $user): array
    {
        $user->load(['roles', 'tenants']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'document_type' => $user->document_type,
            'document_text' => $user->document_text,
            'phone' => $user->phone,
            // Fecha de nacimiento (ítem 37): ver AuthService::transformAuthUser
            // (mismo criterio de formateo 'Y-m-d'; User::birth_date está
            // casteada a 'date' en el modelo).
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'status' => $user->status,
            'role' => $user->getCurrentRole(),
            'roles' => $user->getCurrentRoles(),
            'tenants' => $user->tenants->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'ruc' => $t->ruc,
                'logo_url' => $t->logo_url,
                'is_primary' => $t->pivot->is_primary,
                'supervisor_id' => $t->pivot->supervisor_id ?? null,
            ]),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Update user profile.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);
        $user->load(['roles', 'tenants']);

        return $user;
    }

    /**
     * Upload user avatar.
     *
     * @param User $user
     * @param UploadedFile $file
     * @return string The new avatar URL
     */
    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        // Delete old avatar if exists
        $this->deleteAvatarFile($user);

        // Generate filename
        $filename = 'avatar_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

        // Store new avatar
        $path = $file->storeAs('avatars', $filename, 'public');

        // Update user
        $user->update(['avatar_url' => $path]);
        $user->refresh();

        return $user->avatar_url;
    }

    /**
     * Delete user avatar.
     *
     * @param User $user
     * @return bool
     */
    public function deleteAvatar(User $user): bool
    {
        if ($this->deleteAvatarFile($user)) {
            $user->update(['avatar_url' => null]);
            return true;
        }

        return false;
    }

    /**
     * Delete avatar file from storage.
     *
     * @param User $user
     * @return bool
     */
    protected function deleteAvatarFile(User $user): bool
    {
        $avatarPath = $user->getRawOriginal('avatar_url');
        if (!empty($avatarPath)) {
            Storage::disk('public')->delete($avatarPath);
            return true;
        }

        return false;
    }

    /**
     * Solicita la actualización de datos personales del usuario autenticado.
     *
     * Resuelve la empresa activa del usuario, determina a quién notificar
     * (administradores de esa empresa, o supervisor, o root como último
     * recurso) y envía el correo usando el mailer propio del tenant (con
     * fallback al de la plataforma vía TenantMailerService).
     *
     * @param User $user
     * @param string $message
     * @param string|array|null $requestedChanges
     * @param int|null $activeTenantId Tenant activo resuelto por el controller (header X-Tenant-Ids)
     * @throws \RuntimeException Si no se puede determinar la empresa o los destinatarios
     */
    public function requestDataUpdate(
        User $user,
        string $message,
        string|array|null $requestedChanges,
        ?int $activeTenantId = null
    ): void {
        $tenant = $this->resolveActiveTenant($user, $activeTenantId);

        if (!$tenant) {
            throw new \RuntimeException('No se pudo determinar la empresa asociada a tu cuenta.');
        }

        $recipients = $this->getDataUpdateRecipients($tenant->id, $user);

        if (empty($recipients)) {
            Log::warning('[ProfileService] No se encontraron destinatarios para la solicitud de actualización de datos', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            throw new \RuntimeException('No se encontró un administrador para notificar en tu empresa. Contacta a soporte.');
        }

        $this->tenantMailerService->send(
            $tenant,
            $recipients,
            new DataUpdateRequestMail($user, $tenant, $message, $requestedChanges)
        );

        Log::info('[ProfileService] Solicitud de actualización de datos enviada', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'recipients_count' => count($recipients),
        ]);
    }

    /**
     * Resuelve la empresa activa del usuario: la indicada por el header
     * X-Tenant-Ids (si es una sola y el usuario pertenece a ella), o la
     * primaria del usuario, o la primera disponible como último recurso.
     */
    protected function resolveActiveTenant(User $user, ?int $activeTenantId): ?Tenant
    {
        if ($activeTenantId && $user->belongsToTenant($activeTenantId)) {
            return $user->tenants()->where('tenants.id', $activeTenantId)->first();
        }

        return $user->primaryTenant() ?? $user->tenants()->first();
    }

    /**
     * Obtiene los correos a notificar: administradores de la empresa
     * (rol 'admin' en user_tenant_roles). Si no hay ninguno, cae al
     * supervisor directo del usuario en esa empresa, y como último
     * recurso a los usuarios root de la plataforma.
     *
     * @return array<int, string>
     */
    protected function getDataUpdateRecipients(int $tenantId, User $requester): array
    {
        $adminEmails = User::whereHas('tenantRoles', function ($query) use ($tenantId) {
            $query->where('tenant_id', $tenantId)
                ->whereHas('role', fn ($q) => $q->where('name', 'admin'));
        })
            ->where('id', '!=', $requester->id)
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (!empty($adminEmails)) {
            return $adminEmails;
        }

        $supervisor = $requester->getSupervisorForTenant($tenantId);
        if ($supervisor && $supervisor->email) {
            return [$supervisor->email];
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'root'))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
