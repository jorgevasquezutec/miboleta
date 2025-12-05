<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCookieAccessToken
{
    /**
     * Handle an incoming request.
     *
     * Si existe un access_token en las cookies, lo inyecta en el header Authorization
     * para que Sanctum pueda autenticar la solicitud.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si no hay Authorization header pero sí hay cookie access_token
        if (!$request->bearerToken() && $request->hasCookie('access_token')) {
            $token = $request->cookie('access_token');
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }

        return $next($request);
    }
}

