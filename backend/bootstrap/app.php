<?php

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Exceptions\UserCreationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            // 'tenant' => \App\Http\Middleware\TenantScope::class, // ❌ DEPRECADO - Use TenantFilter
            'tenant.filter' => \App\Http\Middleware\TenantFilter::class, // ✅ Multi-tenant filter
        ]);

        // Configurar CORS para API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\EnsureCookieAccessToken::class,
        ]);

        // ✅ TenantFilter debe ejecutarse DESPUÉS de Sanctum auth
        $middleware->api(append: [
            \App\Http\Middleware\TenantFilter::class,
        ]);

        // Excluir rutas de CSRF (para login público, Swagger y WebSocket auth)
        $middleware->validateCsrfTokens(except: [
            'api/login',
            'api/refresh',
            'api/logout',
            'api/password/forgot',
            'api/password/reset',
            'api/documentation',
            'api/documentation/*',
            'api/broadcasting/auth',
            'broadcasting/auth',
        ]);

        // Configurar credenciales para Sanctum
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Custom Exception: UserCreationException
        $exceptions->renderable(function (UserCreationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Error al crear usuario',
                    'message' => $e->getMessage(),
                ], $e->getCode() ?: 400);
            }
        });

        // Custom Exception: DocumentNotFoundException
        $exceptions->renderable(function (DocumentNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Documento no encontrado',
                    'message' => $e->getMessage(),
                ], $e->getCode() ?: 404);
            }
        });

        // Custom Exception: UnauthorizedAccessException
        $exceptions->renderable(function (UnauthorizedAccessException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Acceso no autorizado',
                    'message' => $e->getMessage(),
                ], $e->getCode() ?: 403);
            }
        });

        // AuthorizationException (Gates de config/access_matrix.php, vía
        // $this->authorize()/$user->can()) -> 403 con el mismo formato que ya
        // usa el resto de la API ("No autorizado"), en vez del texto por
        // defecto de Laravel ("This action is unauthorized.").
        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'No autorizado',
                ], 403);
            }
        });

        // Eloquent ModelNotFoundException -> 404
        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $modelName = class_basename($e->getModel());
                return response()->json([
                    'error' => 'Recurso no encontrado',
                    'message' => "{$modelName} no encontrado",
                ], 404);
            }
        });

        // Symfony NotFoundHttpException -> 404
        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Ruta no encontrada',
                    'message' => 'El endpoint solicitado no existe',
                ], 404);
            }
        });
    })->create();
