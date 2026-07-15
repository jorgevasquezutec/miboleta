<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resuelve la empresa ACTIVA de la petición (la que el usuario tiene
 * seleccionada en el navbar), ya validada por el middleware TenantFilter.
 *
 * CUÁNDO USARLO: solo en endpoints de tipo listado/dashboard/reporte, donde no
 * hay un recurso concreto del cual tomar el tenant.
 *
 * CUÁNDO **NO** USARLO: en acciones sobre un recurso puntual (eliminar un
 * documento, aprobar una solicitud, editar un usuario) el tenant correcto es el
 * DEL RECURSO ($document->tenant_id). Autorizar esas acciones con la empresa
 * "activa" permitiría actuar sobre un recurso de la empresa B usando el rol que
 * el usuario tiene en la A — es la misma clase de fuga que este workstream
 * cierra.
 */
class ActiveTenantResolver
{
    /**
     * @return int|null ID de la empresa activa. null = "todas" (solo root).
     */
    public function resolve(Request $request, User $user): ?int
    {
        // Root es global: puede consultar una empresa concreta o todas.
        if ($user->isRoot()) {
            $requested = $request->query('tenant_id');

            return $requested ? (int) $requested : null;
        }

        // No-root: la empresa activa viene del header X-Tenant-Ids, ya validado
        // contra las empresas del usuario por TenantFilter (_tenant_filter_ids).
        $filterIds = $request->input('_tenant_filter_ids');

        if (is_array($filterIds) && !empty($filterIds)) {
            return (int) $filterIds[0];
        }

        // Sin header (cliente antiguo / petición directa): la empresa primaria.
        return $user->primaryTenant()?->id ?? $user->tenants()->first()?->id;
    }
}
