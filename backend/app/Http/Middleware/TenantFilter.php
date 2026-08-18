<?php

namespace App\Http\Middleware;

use App\Support\TenantAccessCache;
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
     * Rutas de cuenta propia: no devuelven datos de ninguna empresa, así que el
     * filtro no pinta nada en ellas.
     *
     * Van exentas porque este middleware está aplicado a TODA la API
     * (bootstrap/app.php) y un filtro heredado de otra sesión llegaba a
     * bloquear el cambio de contraseña obligatorio: el usuario nuevo recibía
     * 403 y se quedaba sin forma de entrar. En las rutas de datos el 403 se
     * mantiene, que ahí sí es una comprobación de acceso legítima.
     */
    protected const ACCOUNT_ROUTES = [
        'api/password/force-change',
        'api/password/change',
        'api/me',
        'api/logout',
        'api/refresh',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rutas de cuenta propia: nunca se filtran por empresa
        if ($request->is(...self::ACCOUNT_ROUTES)) {
            return $next($request);
        }

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

            // Log::info('🏢 [TenantFilter] Multi-tenant request', [
            //     'user_id' => $user->id,
            //     'user_role' => $user->getCurrentRole(),
            //     'requested_tenants' => $requestedIds,
            //     'count' => count($requestedIds),
            // ]);

            // ✅ EXCEPCIÓN: Root users pueden acceder a TODOS los tenants sin validación
            // isRoot() en vez de getCurrentRole() === 'root': determinístico y
            // sin depender del respaldo global de roles (root es global por
            // diseño). Aquí importa especialmente: este bypass decide si se
            // valida o no que las empresas pedidas sean del usuario.
            if ($user->isRoot()) {
                $request->merge(['_tenant_filter_ids' => $requestedIds]);

                // Log::info('✅ [TenantFilter] Root user - all tenants allowed', [
                //     'user_id' => $user->id,
                //     'tenant_ids' => $requestedIds,
                // ]);

                return $next($request);
            }

            // Obtener tenants del usuario
            $userTenantIds = $this->getUserTenantIds($user);

            // ✅ VALIDACIÓN: Solo permitir tenants a los que el usuario tiene acceso
            $validIds = array_intersect($requestedIds, $userTenantIds);

            if (empty($validIds)) {
                // Log::warning('⚠️ [TenantFilter] Invalid tenant access attempt', [
                //     'user_id' => $user->id,
                //     'requested' => $requestedIds,
                //     'allowed' => $userTenantIds,
                // ]);

                return response()->json([
                    'error' => 'No tienes acceso a las empresas seleccionadas',
                    'message' => 'Las empresas seleccionadas no están asociadas a tu cuenta',
                    'allowed_tenants' => $userTenantIds,
                ], 403);
            }

            // Guardar IDs validados en el request para uso en controllers/scopes
            $request->merge(['_tenant_filter_ids' => $validIds]);

            // Log::info('✅ [TenantFilter] Filter applied', [
            //     'user_id' => $user->id,
            //     'tenant_ids' => $validIds,
            //     'count' => count($validIds),
            // ]);

            return $next($request);
        }

        // ⚠️ RETROCOMPATIBILIDAD: Header legacy X-Tenant-Id (un solo tenant)
        $singleTenantId = $request->header('X-Tenant-Id');

        if ($singleTenantId) {
            $tenantId = intval($singleTenantId);
            $userTenantIds = $this->getUserTenantIds($user);

            // Validar acceso
            if (!in_array($tenantId, $userTenantIds)) {
                // Log::warning('⚠️ [TenantFilter] Invalid single tenant access', [
                //     'user_id' => $user->id,
                //     'requested' => $tenantId,
                //     'allowed' => $userTenantIds,
                // ]);

                return response()->json([
                    'error' => 'No tienes acceso a esta empresa',
                ], 403);
            }

            $request->merge(['_tenant_filter_ids' => [$tenantId]]);

            // Log::info('✅ [TenantFilter] Legacy single tenant filter', [
            //     'user_id' => $user->id,
            //     'tenant_id' => $tenantId,
            // ]);

            return $next($request);
        }

        // ✅ Sin headers: Mostrar todas las empresas del usuario
        // (Solo para usuarios root o cuando no hay filtro específico)
        $scopeHeader = $request->header('X-Tenant-Scope');

        if ($scopeHeader === 'all' || (!$tenantIdsHeader && !$singleTenantId)) {
            // Non-root users with scope=all: restrict to their own tenants
            if ($scopeHeader === 'all' && !$user->isRoot()) {
                $userTenantIds = $this->getUserTenantIds($user);
                $request->merge(['_tenant_filter_ids' => $userTenantIds]);
            }

            // Root users or no explicit scope: no filter (scopes decide by role)
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
        // Cache de 1 hora para evitar queries repetitivas. Se invalida desde
        // TenantAccessCache::forget() en cada cambio de empresas del usuario.
        return cache()->remember(
            TenantAccessCache::activeTenantIdsKey($user->id),
            TenantAccessCache::TTL,
            function () use ($user) {
                // ✅ Solo retornar tenants activos
                return $user->tenants()
                    ->where('tenants.status', 'active')
                    ->pluck('tenants.id')
                    ->map(fn($id) => (int) $id)
                    ->toArray();
            }
        );
    }
}
