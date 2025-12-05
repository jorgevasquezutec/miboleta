<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantScope
{
    /**
     * Handle an incoming request.
     *
     * Este middleware establece el tenant actual basado en:
     * 1. Header X-Tenant-ID
     * 2. Query parameter ?tenant_id=
     * 3. Tenant primario del usuario
     * 
     * También verifica que el usuario tenga acceso al tenant solicitado.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        // Obtener tenant_id del header o query
        $tenantId = $request->header('X-Tenant-ID') 
                    ?? $request->query('tenant_id');

        // Si no se especifica, usar el tenant primario del usuario
        if (!$tenantId) {
            $primaryTenant = $user->primaryTenant();
            $tenantId = $primaryTenant?->id;
            
            if (!$tenantId) {
                return response()->json([
                    'message' => 'Tenant ID requerido. Use header X-Tenant-ID o query parameter tenant_id.',
                ], 400);
            }
        }

        // Verificar que el usuario tenga acceso al tenant
        if (!$user->isRoot() && !$user->belongsToTenant($tenantId)) {
            return response()->json([
                'message' => 'No tienes acceso a este tenant.',
                'tenant_id' => $tenantId,
            ], 403);
        }

        // Agregar tenant_id al request para uso posterior
        $request->merge(['current_tenant_id' => $tenantId]);

        return $next($request);
    }
}
