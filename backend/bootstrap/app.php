<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'tenant' => \App\Http\Middleware\TenantScope::class,
        ]);

        // Configurar CORS para API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\EnsureCookieAccessToken::class,
        ]);

        // Excluir rutas de CSRF (para login público y Swagger)
        $middleware->validateCsrfTokens(except: [
            'api/login',
            'api/refresh',
            'api/documentation',
            'api/documentation/*',
        ]);

        // Configurar credenciales para Sanctum
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
