<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Verifica que el usuario autenticado tenga al menos uno de los roles permitidos.
     * 
     * Uso: Route::middleware('role:admin,root')
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado.',
            ], 401);
        }

        // Si no se especifican roles, solo verifica autenticación
        if (empty($roles)) {
            return $next($request);
        }

        // Verificar si el usuario tiene alguno de los roles permitidos
        $userRoles = $request->user()->getCurrentRoles();
        
        $hasRole = !empty(array_intersect($roles, $userRoles));

        if (!$hasRole) {
            return response()->json([
                'message' => 'No tienes permisos para acceder a este recurso.',
                'required_roles' => $roles,
                'your_roles' => $userRoles,
            ], 403);
        }

        return $next($request);
    }
}
