<?php

namespace App\Policies;

use App\Models\User;

class TenantPolicy
{
    /**
     * Determinar si el usuario puede ver cualquier tenant
     */
    public function viewAny(User $user): bool
    {
        return $user->isRoot();
    }

    /**
     * Determinar si el usuario puede ver un tenant específico
     */
    public function view(User $user, $tenant): bool
    {
        // Root puede ver todos
        if ($user->isRoot()) {
            return true;
        }

        // Verificar si el usuario pertenece al tenant
        return $user->belongsToTenant($tenant->id);
    }

    /**
     * Determinar si el usuario puede crear tenants
     */
    public function create(User $user): bool
    {
        return $user->isRoot();
    }

    /**
     * Determinar si el usuario puede actualizar un tenant
     */
    public function update(User $user, $tenant): bool
    {
        // Root puede actualizar todos
        if ($user->isRoot()) {
            return true;
        }

        // Admin puede actualizar su propio tenant
        return $user->isAdmin() && $user->belongsToTenant($tenant->id);
    }

    /**
     * Determinar si el usuario puede eliminar un tenant
     */
    public function delete(User $user, $tenant): bool
    {
        // Solo root puede eliminar tenants
        return $user->isRoot();
    }

    /**
     * Determinar si el usuario puede gestionar usuarios de un tenant
     */
    public function manageUsers(User $user, $tenant): bool
    {
        // Root puede gestionar usuarios de todos los tenants
        if ($user->isRoot()) {
            return true;
        }

        // Admin puede gestionar usuarios de su tenant
        return $user->isAdmin() && $user->belongsToTenant($tenant->id);
    }
}
