<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determinar si el usuario puede ver la lista de usuarios
     */
    public function viewAny(User $user): bool
    {
        // Root y Admin pueden ver usuarios
        return $user->isRoot() || $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede ver otro usuario específico
     */
    public function view(User $user, User $model): bool
    {
        // Root puede ver todos
        if ($user->isRoot()) {
            return true;
        }

        // Puede ver su propio perfil
        if ($user->id === $model->id) {
            return true;
        }

        // Admin puede ver usuarios de su tenant
        if ($user->isAdmin()) {
            $userTenantIds = $user->tenants()->pluck('tenants.id');
            $modelTenantIds = $model->tenants()->pluck('tenants.id');
            return $userTenantIds->intersect($modelTenantIds)->isNotEmpty();
        }
        
        return false;
    }

    /**
     * Determinar si el usuario puede crear usuarios
     */
    public function create(User $user): bool
    {
        // Root y Admin pueden crear usuarios
        return $user->isRoot() || $user->isAdmin();
    }

    /**
     * Determinar si el usuario puede actualizar otro usuario
     */
    public function update(User $user, User $model): bool
    {
        // Root puede actualizar todos
        if ($user->isRoot()) {
            return true;
        }

        // Puede actualizar su propio perfil (campos limitados)
        if ($user->id === $model->id) {
            return true;
        }

        // Admin puede actualizar usuarios de su tenant
        if ($user->isAdmin()) {
            $userTenantIds = $user->tenants()->pluck('tenants.id');
            $modelTenantIds = $model->tenants()->pluck('tenants.id');
            return $userTenantIds->intersect($modelTenantIds)->isNotEmpty();
        }
        
        return false;
    }

    /**
     * Determinar si el usuario puede eliminar otro usuario
     */
    public function delete(User $user, User $model): bool
    {
        // No puede eliminarse a sí mismo
        if ($user->id === $model->id) {
            return false;
        }

        // Root puede eliminar cualquier usuario
        if ($user->isRoot()) {
            return true;
        }

        // Admin puede eliminar usuarios de su tenant (excepto otros admins)
        if ($user->isAdmin() && !$model->isAdmin() && !$model->isRoot()) {
            $userTenantIds = $user->tenants()->pluck('tenants.id');
            $modelTenantIds = $model->tenants()->pluck('tenants.id');
            return $userTenantIds->intersect($modelTenantIds)->isNotEmpty();
        }
        
        return false;
    }

    /**
     * Determinar si el usuario puede asignar roles
     */
    public function assignRole(User $user, User $model): bool
    {
        // Root puede asignar cualquier rol
        if ($user->isRoot()) {
            return true;
        }

        // Admin puede asignar roles (excepto root y admin) en su tenant
        if ($user->isAdmin()) {
            $userTenantIds = $user->tenants()->pluck('tenants.id');
            $modelTenantIds = $model->tenants()->pluck('tenants.id');
            return $userTenantIds->intersect($modelTenantIds)->isNotEmpty();
        }

        return false;
    }
}
