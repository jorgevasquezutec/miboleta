<?php

namespace App\Models\Scopes;

use App\Support\TenantAccessCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Global Scope para filtrado automático por tenant
 * 
 * Este scope se aplica automáticamente a todos los modelos que lo usen,
 * filtrando los resultados según los tenant IDs procesados por el middleware TenantFilter.
 * 
 * Comportamiento:
 * - Si hay _tenant_filter_ids en request: WHERE IN tenant_id
 * - Si no hay filtro pero usuario no es root: WHERE IN user's tenants
 * - Si usuario es root sin filtro: Sin restricción
 * 
 * Uso en modelos:
 * ```php
 * use App\Models\Scopes\TenantFilterScope;
 * 
 * protected static function booted()
 * {
 *     static::addGlobalScope(new TenantFilterScope);
 * }
 * ```
 */
class TenantFilterScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     * 
     * @param Builder<Model> $builder
     * @param Model $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Obtener tenant IDs del request (procesados por middleware)
        $tenantIds = request()->get('_tenant_filter_ids');

        if ($tenantIds && is_array($tenantIds) && count($tenantIds) > 0) {
            $tableName = $model->getTable();
            $builder->whereIn($tableName . '.tenant_id', $tenantIds);

            // Log::debug('🔍 [TenantFilterScope] Applied filter', [
            //     'model' => get_class($model),
            //     'tenant_ids' => $tenantIds,
            //     'count' => count($tenantIds),
            // ]);

            return;
        }

        // Sin filtro explícito: Aplicar restricción según rol del usuario
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user) {
            // Sin usuario autenticado: Sin restricción
            // Log::debug('🔍 [TenantFilterScope] No user - no filter', [
            //     'model' => get_class($model),
            // ]);
            return;
        }

        // Si es usuario root sin filtro: Puede ver todo
        if ($user->isRoot()) {
            // Log::debug('🔍 [TenantFilterScope] Root user - no filter', [
            //     'model' => get_class($model),
            //     'user_id' => $user->id,
            // ]);
            return;
        }

        // Usuario no-root sin filtro: Restringir a sus tenants
        // Se invalida desde TenantAccessCache::forget() en cada cambio de
        // empresas del usuario (ver App\Support\TenantAccessCache).
        $userTenantIds = cache()->remember(
            TenantAccessCache::tenantIdsKey($user->id),
            TenantAccessCache::TTL,
            function () use ($user) {
                // ✅ FIX: Especificar tenants.id para evitar ambigüedad en JOIN
                return $user->tenants()->pluck('tenants.id')->map(fn($id) => (int) $id)->toArray();
            }
        );

        if (!empty($userTenantIds)) {
            $tableName = $model->getTable();
            $builder->whereIn($tableName . '.tenant_id', $userTenantIds);

            // Log::debug('🔍 [TenantFilterScope] User tenants filter', [
            //     'model' => get_class($model),
            //     'user_id' => $user->id,
            //     'tenant_ids' => $userTenantIds,
            // ]);
        }
    }
}
