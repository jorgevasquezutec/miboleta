<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TenantService
{
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
        return Tenant::create([
            'name' => $data['name'],
            'ruc' => $data['ruc'],
            'business_name' => $data['business_name'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);
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

        $tenant->update($data);

        // If status changed, clear the active tenant IDs cache for all users of this tenant
        if ($statusChanged) {
            $userIds = $tenant->users()->pluck('users.id');
            foreach ($userIds as $userId) {
                cache()->forget("user:{$userId}:active_tenant_ids");
            }
        }

        return $tenant->fresh();
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
        return $tenant->delete();
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
        return $user->isRoot() || $tenant->hasUser($user);
    }

    /**
     * Transform tenant for list response.
     *
     * @param Tenant $tenant
     * @return array
     */
    public function transformTenantForList(Tenant $tenant): array
    {
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
            'users_count' => $tenant->users()->count(),
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
