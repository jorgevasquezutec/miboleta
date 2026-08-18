<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Caché de las empresas a las que pertenece un usuario.
 *
 * Dos consumidores la leen con TTL de una hora:
 * - `App\Http\Middleware\TenantFilter`: valida el header X-Tenant-Ids contra las
 *   empresas ACTIVAS del usuario (`active_tenant_ids`).
 * - `App\Models\Scopes\TenantFilterScope`: acota las consultas de los usuarios
 *   no-root a sus empresas (`tenant_ids`).
 *
 * Las claves viven aquí para que quien las llena y quien las vacía no puedan
 * divergir: antes solo se invalidaban al cambiar el estado de una empresa, así
 * que asignar o quitar empresas a un usuario tardaba hasta una hora en surtir
 * efecto y mientras tanto el backend le respondía 403 aunque su ficha ya
 * estuviera correcta.
 */
final class TenantAccessCache
{
    /** Duración de ambas cachés, en segundos. */
    public const TTL = 3600;

    /** Clave de todas las empresas del usuario (TenantFilterScope). */
    public static function tenantIdsKey(int|string $userId): string
    {
        return "user:{$userId}:tenant_ids";
    }

    /** Clave de las empresas ACTIVAS del usuario (TenantFilter). */
    public static function activeTenantIdsKey(int|string $userId): string
    {
        return "user:{$userId}:active_tenant_ids";
    }

    /**
     * Invalida ambas cachés de uno o varios usuarios.
     *
     * Debe llamarse ante cualquier cambio en `user_tenants` (alta, edición,
     * borrado de usuario, asignación/retiro de empresa, carga masiva) y ante un
     * cambio de estado de una empresa.
     *
     * @param int|string|array<int, int|string>|\Illuminate\Support\Collection $userIds
     */
    public static function forget(int|string|array|\Illuminate\Support\Collection $userIds): void
    {
        foreach (collect($userIds)->flatten()->filter()->unique() as $userId) {
            Cache::forget(self::tenantIdsKey($userId));
            Cache::forget(self::activeTenantIdsKey($userId));
        }
    }
}
