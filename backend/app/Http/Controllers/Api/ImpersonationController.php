<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\Request;

/**
 * "Iniciar sesión como" (impersonation): root opera con la identidad de otro
 * usuario para reproducir exactamente lo que ve —mismas abilities, misma
 * lógica de negocio— sin alterar autorización. Lo único que cambia es que
 * queda rastro de quién estaba detrás (ver AuthService::impersonate() y
 * AuditService::log()).
 *
 * Controller propio y no un método más de AuthController/UserController:
 * start() no es un login real (no valida password, no es el actor
 * autenticándose a sí mismo) y leave() no encaja en la gestión CRUD de
 * usuarios; el flujo de entrada/salida se lee mejor junto, en un solo sitio.
 */
class ImpersonationController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected AuditService $auditService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/users/{id}/impersonate",
     *     tags={"Autenticación"},
     *     summary="Iniciar sesión como otro usuario",
     *     description="Solo root (ability 'users.impersonate'). Emite una sesión con la identidad del usuario indicado sin revocar la sesión real del propio root ni la del usuario impersonado.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Sesión impersonada iniciada"),
     *     @OA\Response(response=403, description="No autorizado, autoimpersonación, objetivo root o impersonación ya activa"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function start(Request $request, $id)
    {
        $this->authorize('users.impersonate');

        $root = $request->user();

        // Impersonación anidada: hay un "boleto de vuelta" sin consumir de una
        // sesión anterior. Forzar a salir primero (POST /api/impersonate/leave)
        // en vez de pisarlo, que lo dejaría huérfano y sin revocar.
        if ($request->cookie('impersonator_return')) {
            return response()->json([
                'message' => 'Ya tienes una sesión de impersonación activa. Sal de ella antes de iniciar otra.',
            ], 403);
        }

        // Sin withTrashed: una cuenta eliminada no se impersona (findOrFail
        // aplica el scope global de SoftDeletes y responde 404 directo).
        $target = User::findOrFail($id);

        if ($target->id === $root->id) {
            return response()->json(['message' => 'No puedes impersonarte a ti mismo.'], 403);
        }

        if ($target->isRoot()) {
            return response()->json(['message' => 'No puedes impersonar a otra cuenta root.'], 403);
        }

        $result = $this->authService->impersonate($root, $target, $request->ip(), $request->userAgent());

        $this->auditService->logImpersonationStarted($root->id, $target->id);

        return response()->json([
            'user' => $this->authService->transformAuthUser($result['user']),
            // Matriz de Accesos: mismo criterio que /login y /me.
            'access_matrix' => config('access_matrix'),
            'impersonator' => [
                'id' => $root->id,
                'full_name' => $root->full_name,
                'email' => $root->email,
            ],
        ])
            ->cookie($this->authService->createAccessTokenCookie($result['access_token']))
            ->cookie($this->authService->createRefreshTokenCookie($result['refresh_token']->token))
            ->cookie($this->authService->createImpersonatorReturnCookie($result['impersonator_return_token']->token));
    }

    /**
     * @OA\Post(
     *     path="/api/impersonate/leave",
     *     tags={"Autenticación"},
     *     summary="Salir de una sesión impersonada",
     *     description="Restaura la sesión real de root. Revoca únicamente los tokens creados al iniciar la impersonación.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Sesión de root restaurada"),
     *     @OA\Response(response=422, description="No hay una sesión de impersonación activa")
     * )
     */
    public function leave(Request $request)
    {
        // Se lee ANTES de que leaveImpersonation() revoque el token de
        // impersonación: sirve para identificar al empleado en el audit log
        // de abajo, y seguirá siendo válido tras la llamada porque revocar la
        // fila en BD no invalida el objeto ya resuelto en esta request.
        $impersonatedUserId = $request->user()->id;

        $result = $this->authService->leaveImpersonation(
            $request->user(),
            $request->cookie('impersonator_return'),
            $request->cookie('refresh_token'),
            $request->ip(),
            $request->userAgent()
        );

        if (!$result) {
            // Se expira el boleto de vuelta TAMBIÉN al fallar. Si no, un
            // ticket vencido (TTL 8 h, mientras la sesión impersonada dura 30
            // días: basta con trabajar una jornada larga) o revocado desde
            // otro dispositivo deja al usuario atascado — leave() responde
            // 422 pero la cookie sigue ahí, así que start() responde 403 y
            // "Volver a mi cuenta" no hace nada. Limpiarla aquí desatasca el
            // botón sin obligar a pasar por Logout.
            return response()->json([
                'message' => 'No hay una sesión de impersonación activa.',
            ], 422)
                ->cookie($this->authService->createExpiredCookie('impersonator_return'));
        }

        // El actor de este evento es el ROOT que estaba detrás (mismo
        // criterio que logImpersonationStarted), no el empleado cuya
        // identidad se dejó de usar.
        $this->auditService->logImpersonationStopped($result['user']->id, $impersonatedUserId);

        return response()->json([
            'user' => $this->authService->transformAuthUser($result['user']),
            'access_matrix' => config('access_matrix'),
        ])
            ->cookie($this->authService->createAccessTokenCookie($result['access_token']))
            ->cookie($this->authService->createRefreshTokenCookie($result['refresh_token']->token))
            ->cookie($this->authService->createExpiredCookie('impersonator_return'));
    }
}
