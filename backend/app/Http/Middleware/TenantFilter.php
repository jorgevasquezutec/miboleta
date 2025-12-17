<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware para filtrado multi-tenant
 * 
 * Procesa el header X-Tenant-Ids enviado desde el frontend y valida
 * que el usuario tenga acceso a los tenants solicitados.
 * 
 * Headers soportados:
 * - X-Tenant-Ids: "1,2,3" (múltiples tenants)
 * - X-Tenant-Id: "1" (legacy, un solo tenant)
 * - X-Tenant-Scope: "all" (todas las empresas del usuario)
 */
class TenantFilter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Si no hay usuario autenticado, continuar sin filtro
        if (!$user) {
            return $next($request);
        }

        // Obtener tenant IDs del header X-Tenant-Ids (nuevo sistema multi-tenant)
        $tenantIdsHeader = $request->header('X-Tenant-Ids');

        // 🔍 DEBUG: Try different cases
        if (!$tenantIdsHeader) {
            // Log::debug('🔍 [TenantFilter] Trying different header cases', [
            //     'X-Tenant-Ids' => $request->header('X-Tenant-Ids'),
            //     'x-tenant-ids' => $request->header('x-tenant-ids'),
            //     'X-tenant-ids' => $request->header('X-tenant-ids'),
            // ]);

            // Try lowercase
            $tenantIdsHeader = $request->header('x-tenant-ids');
        }

        // ✅ NUEVO: Header con múltiples tenant IDs
        if ($tenantIdsHeader) {
            $requestedIds = array_map('intval', array_filter(explode(',', $tenantIdsHeader)));

            Log::info('🏢 [TenantFilter] Multi-tenant request', [
                'user_id' => $user->id,
                'user_role' => $user->getCurrentRole(),
                'requested_tenants' => $requestedIds,
                'count' => count($requestedIds),
            ]);

            // ✅ EXCEPCIÓN: Root users pueden acceder a TODOS los tenants sin validación
            if ($user->getCurrentRole() === 'root') {
                $request->merge(['_tenant_filter_ids' => $requestedIds]);

                Log::info('✅ [TenantFilter] Root user - all tenants allowed', [
                    'user_id' => $user->id,
                    'tenant_ids' => $requestedIds,
                ]);

                return $next($request);
            }

            // Obtener tenants del usuario
            $userTenantIds = $this->getUserTenantIds($user);

            // ✅ VALIDACIÓN: Solo permitir tenants a los que el usuario tiene acceso
            $validIds = array_intersect($requestedIds, $userTenantIds);

            if (empty($validIds)) {
                Log::warning('⚠️ [TenantFilter] Invalid tenant access attempt', [
                    'user_id' => $user->id,
                    'requested' => $requestedIds,
                    'allowed' => $userTenantIds,
                ]);

                return response()->json([
                    'error' => 'No tienes acceso a las empresas seleccionadas',
                    'message' => 'Las empresas seleccionadas no están asociadas a tu cuenta',
                    'allowed_tenants' => $userTenantIds,
                ], 403);
            }

            // Guardar IDs validados en el request para uso en controllers/scopes
            $request->merge(['_tenant_filter_ids' => $validIds]);

            Log::info('✅ [TenantFilter] Filter applied', [
                'user_id' => $user->id,
                'tenant_ids' => $validIds,
                'count' => count($validIds),
            ]);

            return $next($request);
        }

        // ⚠️ RETROCOMPATIBILIDAD: Header legacy X-Tenant-Id (un solo tenant)
        $singleTenantId = $request->header('X-Tenant-Id');

        if ($singleTenantId) {
            $tenantId = intval($singleTenantId);
            $userTenantIds = $this->getUserTenantIds($user);

            // Validar acceso
            if (!in_array($tenantId, $userTenantIds)) {
                Log::warning('⚠️ [TenantFilter] Invalid single tenant access', [
                    'user_id' => $user->id,
                    'requested' => $tenantId,
                    'allowed' => $userTenantIds,
                ]);

                return response()->json([
                    'error' => 'No tienes acceso a esta empresa',
                ], 403);
            }

            $request->merge(['_tenant_filter_ids' => [$tenantId]]);

            Log::info('✅ [TenantFilter] Legacy single tenant filter', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);

            return $next($request);
        }

        // ✅ Sin headers: Mostrar todas las empresas del usuario
        // (Solo para usuarios root o cuando no hay filtro específico)
        $scopeHeader = $request->header('X-Tenant-Scope');

        if ($scopeHeader === 'all' || (!$tenantIdsHeader && !$singleTenantId)) {
            // Log::info('🏢 [TenantFilter] No filter - showing all user tenants', [
            //     'user_id' => $user->id,
            //     'role' => $user->getCurrentRole(),
            // ]);

            // No establecer _tenant_filter_ids para indicar "sin filtro"
            // Los scopes decidirán si filtrar por rol
            return $next($request);
        }

        return $next($request);
    }

    /**
     * Obtiene los IDs de tenants del usuario con cache
     * 
     * @param \App\Models\User $user
     * @return array
     */
    protected function getUserTenantIds($user): array
    {
        // Cache de 1 hora para evitar queries repetitivas
        return cache()->remember(
            "user:{$user->id}:tenant_ids",
            3600,
            function () use ($user) {
                // ✅ FIX: Especificar tenants.id para evitar ambigüedad en JOIN
                return $user->tenants()->pluck('tenants.id')->map(fn($id) => (int) $id)->toArray();
            }
        );
    }
}
