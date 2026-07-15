<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantService
{
    public function __construct(
        protected TenantMailerService $tenantMailerService,
        protected AuditService $auditService
    ) {
    }

    /**
     * Snapshot auditable de una empresa: incluye los campos relevantes pero
     * NUNCA la contraseña SMTP (solo un booleano has_mail_password).
     */
    protected function auditableTenantSnapshot(Tenant $tenant): array
    {
        return [
            'name' => $tenant->name,
            'ruc' => $tenant->ruc,
            'business_name' => $tenant->business_name,
            'address' => $tenant->address,
            'phone' => $tenant->phone,
            'status' => $tenant->status,
            'labor_regime' => $tenant->labor_regime,
            'mail_host' => $tenant->mail_host,
            'mail_port' => $tenant->mail_port,
            'mail_username' => $tenant->mail_username,
            'mail_encryption' => $tenant->mail_encryption,
            'mail_from_address' => $tenant->mail_from_address,
            'mail_from_name' => $tenant->mail_from_name,
            'has_mail_password' => !empty($tenant->mail_password),
        ];
    }

    /**
     * Get tenants list based on user role.
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getTenants(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Tenant::query();

        // Scope: Root sees all, others see only their tenants
        if (!$user->isRoot()) {
            $tenantIds = $user->tenants->pluck('id');
            $query->whereIn('id', $tenantIds);
        }

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a single tenant.
     *
     * @param string $id
     * @param User $user
     * @return Tenant
     * @throws UnauthorizedAccessException
     */
    public function getTenant(string $id, User $user): Tenant
    {
        $tenant = Tenant::findOrFail($id);

        if (!$this->canAccessTenant($user, $tenant)) {
            throw new UnauthorizedAccessException('No autorizado para ver este tenant');
        }

        return $tenant;
    }

    /**
     * Create a new tenant.
     *
     * @param array $data
     * @return Tenant
     */
    public function createTenant(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'ruc' => $data['ruc'],
            'business_name' => $data['business_name'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'status' => $data['status'] ?? 'active',
            'labor_regime' => $data['labor_regime'] ?? 'general',
            // Configuración SMTP propia de la empresa (RP2-B); ver
            // TenantMailerService. Todo opcional: si no se envía, la
            // empresa usa el mailer por defecto de la plataforma.
            'mail_host' => $data['mail_host'] ?? null,
            'mail_port' => $data['mail_port'] ?? null,
            'mail_username' => $data['mail_username'] ?? null,
            'mail_password' => $data['mail_password'] ?? null,
            'mail_encryption' => $data['mail_encryption'] ?? null,
            'mail_from_address' => $data['mail_from_address'] ?? null,
            'mail_from_name' => $data['mail_from_name'] ?? null,
        ]);

        $this->auditService->logTenantCreated($tenant->id, $this->auditableTenantSnapshot($tenant));

        return $tenant;
    }

    /**
     * Update a tenant.
     *
     * @param Tenant $tenant
     * @param array $data
     * @return Tenant
     */
    public function updateTenant(Tenant $tenant, array $data): Tenant
    {
        $statusChanged = isset($data['status']) && $data['status'] !== $tenant->status;

        $oldSnapshot = $this->auditableTenantSnapshot($tenant);

        $tenant->update($data);

        // If status changed, clear the active tenant IDs cache for all users of this tenant
        if ($statusChanged) {
            $userIds = $tenant->users()->pluck('users.id');
            foreach ($userIds as $userId) {
                cache()->forget("user:{$userId}:active_tenant_ids");
            }
        }

        $fresh = $tenant->fresh();

        $this->auditService->logTenantUpdated(
            $tenant->id,
            $oldSnapshot,
            $this->auditableTenantSnapshot($fresh)
        );

        return $fresh;
    }

    /**
     * Delete a tenant.
     *
     * @param string $id
     * @param User $user
     * @return bool
     * @throws UnauthorizedAccessException
     */
    public function deleteTenant(string $id, User $user): bool
    {
        if (!$user->isRoot()) {
            throw new UnauthorizedAccessException('No autorizado. Solo el administrador de plataforma puede eliminar tenants.');
        }

        $tenant = Tenant::findOrFail($id);
        $snapshot = $this->auditableTenantSnapshot($tenant);
        $tenantId = $tenant->id;

        $deleted = $tenant->delete();

        if ($deleted) {
            $this->auditService->logTenantDeleted($tenantId, $snapshot);
        }

        return $deleted;
    }

    /**
     * Get users of a tenant.
     *
     * @param string $tenantId
     * @param User $currentUser
     * @return \Illuminate\Database\Eloquent\Collection
     * @throws UnauthorizedAccessException
     */
    public function getTenantUsers(string $tenantId, User $currentUser)
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (!$this->canAccessTenant($currentUser, $tenant)) {
            throw new UnauthorizedAccessException('No autorizado para ver usuarios de este tenant');
        }

        return $tenant->users()->with('roles')->get();
    }

    /**
     * Add a user to a tenant.
     *
     * @param string $tenantId
     * @param string $userId
     * @param bool $isPrimary
     * @return array ['success' => bool, 'message' => string]
     */
    public function addUserToTenant(string $tenantId, string $userId, bool $isPrimary = false): array
    {
        $tenant = Tenant::findOrFail($tenantId);
        $user = User::findOrFail($userId);

        if ($tenant->hasUser($user)) {
            return [
                'success' => false,
                'message' => 'El usuario ya está asignado a este tenant',
            ];
        }

        $tenant->users()->attach($user->id, ['is_primary' => $isPrimary]);

        return [
            'success' => true,
            'message' => 'Usuario agregado al tenant exitosamente',
        ];
    }

    /**
     * Remove a user from a tenant.
     *
     * @param string $tenantId
     * @param string $userId
     * @param User $currentUser
     * @return array ['success' => bool, 'message' => string]
     * @throws UnauthorizedAccessException
     */
    public function removeUserFromTenant(string $tenantId, string $userId, User $currentUser): array
    {
        $tenant = Tenant::findOrFail($tenantId);

        if (!$this->canManageTenantUsers($currentUser, $tenant)) {
            throw new UnauthorizedAccessException('No autorizado para gestionar usuarios de este tenant');
        }

        $userToRemove = User::findOrFail($userId);

        if (!$tenant->hasUser($userToRemove)) {
            return [
                'success' => false,
                'message' => 'El usuario no está asignado a este tenant',
            ];
        }

        // Check if primary tenant
        $pivot = $tenant->users()->where('users.id', $userId)->first()->pivot;
        if ($pivot->is_primary) {
            return [
                'success' => false,
                'message' => 'No se puede remover el tenant primario del usuario. Asigna otro tenant como primario primero.',
            ];
        }

        $tenant->users()->detach($userId);

        return [
            'success' => true,
            'message' => 'Usuario removido del tenant exitosamente',
        ];
    }

    /**
     * Prueba la conexión SMTP configurada para un tenant (botón "Probar
     * conexión", ítem 24-menor). Solo root: es información sensible del
     * servidor de correo de la empresa (host/usuario/password) y el
     * handshake SMTP puede usarse para sondear la red desde el servidor.
     *
     * @param string $id
     * @param User $user
     * @return array{success: bool, message: string}
     * @throws UnauthorizedAccessException
     */
    public function testMailerConnection(string $id, User $user): array
    {
        if (!$user->isRoot()) {
            throw new UnauthorizedAccessException('No autorizado. Solo el administrador de plataforma puede probar la conexión SMTP.');
        }

        $tenant = Tenant::findOrFail($id);

        if (!$tenant->hasCustomMailer()) {
            return [
                'success' => false,
                'message' => 'Esta empresa no tiene un servidor SMTP propio configurado (falta host o correo remitente).',
            ];
        }

        $success = $this->tenantMailerService->testConnection([
            'host' => $tenant->mail_host,
            'port' => $tenant->mail_port ?? 587,
            'username' => $tenant->mail_username,
            'password' => $tenant->mail_password,
            'encryption' => $tenant->mail_encryption,
        ]);

        return [
            'success' => $success,
            'message' => $success
                ? 'Conexión exitosa con el servidor SMTP.'
                : 'No se pudo conectar con el servidor SMTP. Verifica host, puerto, usuario, contraseña y cifrado.',
        ];
    }

    /**
     * Check if user can access a tenant.
     *
     * @param User $user
     * @param Tenant $tenant
     * @return bool
     */
    public function canAccessTenant(User $user, Tenant $tenant): bool
    {
        if ($user->isRoot()) {
            return true;
        }

        return $tenant->hasUser($user);
    }

    /**
     * Check if user can manage tenant users.
     *
     * @param User $user
     * @param Tenant $tenant
     * @return bool
     */
    public function canManageTenantUsers(User $user, Tenant $tenant): bool
    {
        // Gestionar la membresía de una empresa es 'tenants.assign_users'
        // (misma ability que TenantController::addUser vía AssignTenantsRequest;
        // quitar es el inverso de asignar). El rol se resuelve en la empresa DEL
        // RECURSO, no en la activa de la sesión.
        //
        // Antes: root || $tenant->hasUser($user) — sin mirar el rol, así que
        // CUALQUIER miembro (incluido un client) podía sacar usuarios de su
        // propia empresa. Escalada de privilegios, no solo desvío de la matriz.
        return $user->can('tenants.assign_users', $tenant->id);
    }

    /**
     * Transform tenant for list response.
     *
     * @param Tenant $tenant
     * @return array
     */
    public function transformTenantForList(Tenant $tenant): array
    {
        $usersCount = $tenant->users()->count();
        $initialEmployeeCount = (int) ($tenant->initial_employee_count ?? 0);

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'ruc' => $tenant->ruc,
            'business_name' => $tenant->business_name,
            'address' => $tenant->address,
            'phone' => $tenant->phone,
            'logo_path' => $tenant->logo_path,
            'logo_url' => $tenant->logo_url,
            'status' => $tenant->status,
            'users_count' => $usersCount,
            // Contador de empleados (RP1-C): ver UserBatch::syncInitialEmployeeCounts
            'initial_employee_count' => $initialEmployeeCount,
            'current_employee_count' => $usersCount,
            'subsequent_employee_count' => max(0, $usersCount - $initialEmployeeCount),
            // Régimen laboral para el cómputo de vacaciones (RP2-A)
            'labor_regime' => $tenant->labor_regime,
            // Configuración SMTP propia de la empresa (RP2-B). mail_password
            // NUNCA se expone; solo un booleano que indica si ya hay una
            // guardada (ver Tenant::$hidden y TenantMailerService).
            'mail_host' => $tenant->mail_host,
            'mail_port' => $tenant->mail_port,
            'mail_username' => $tenant->mail_username,
            'mail_encryption' => $tenant->mail_encryption,
            'mail_from_address' => $tenant->mail_from_address,
            'mail_from_name' => $tenant->mail_from_name,
            'has_mail_password' => !empty($tenant->mail_password),
            'has_custom_mailer' => $tenant->hasCustomMailer(),
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
        ];
    }

    /**
     * Transform user for tenant users response.
     *
     * @param User $user
     * @return array
     */
    public function transformUserForTenant(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'document_type' => $user->document_type,
            'document_text' => $user->document_text,
            'phone' => $user->phone,
            'status' => $user->status,
            'role' => $user->getCurrentRole(),
            'is_primary' => $user->pivot->is_primary ?? false,
            'created_at' => $user->created_at,
        ];
    }
}
