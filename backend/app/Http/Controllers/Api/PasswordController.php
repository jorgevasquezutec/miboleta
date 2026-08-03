<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminResetPasswordRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForceChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PasswordService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Contraseñas",
 *     description="Gestión de contraseñas (recuperación, cambio, reset)"
 * )
 */
class PasswordController extends Controller
{
    public function __construct(
        protected PasswordService $passwordService,
        protected AuditService $auditService,
        protected UserService $userService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/password/forgot",
     *     tags={"Contraseñas"},
     *     summary="Solicitar recuperación de contraseña",
     *     description="Envía un email con link de recuperación de contraseña",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email enviado (siempre responde éxito por seguridad)",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Si el correo existe, recibirás un enlace de recuperación.")
     *         )
     *     )
     * )
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->passwordService->requestPasswordReset($validated['email']);

        $this->auditService->logPasswordResetRequested($validated['email']);

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace de recuperación.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/password/reset",
     *     tags={"Contraseñas"},
     *     summary="Restablecer contraseña con token",
     *     description="Restablece la contraseña usando el token recibido por email",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "token", "password", "password_confirmation"},
     *             @OA\Property(property="email", type="string", format="email", example="usuario@empresa.com"),
     *             @OA\Property(property="token", type="string", example="abc123token"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="NuevaPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NuevaPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña restablecida exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contraseña restablecida correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Token inválido o expirado"
     *     )
     * )
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->passwordService->resetPasswordWithToken(
            $validated['email'],
            $validated['token'],
            $validated['password']
        );

        if (!$result['success']) {
            $statusCode = str_contains($result['message'], 'no encontrado') ? 404 : 422;
            return response()->json(['message' => $result['message']], $statusCode);
        }

        $resetUser = User::where('email', $validated['email'])->first();
        if ($resetUser) {
            $this->auditService->logPasswordReset($resetUser->id, ['via' => 'token']);
        }

        return response()->json(['message' => $result['message']]);
    }

    /**
     * @OA\Post(
     *     path="/api/password/change",
     *     tags={"Contraseñas"},
     *     summary="Cambiar contraseña",
     *     description="Cambia la contraseña del usuario autenticado",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "password", "password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="PasswordActual"),
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="NuevaPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NuevaPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña cambiada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contraseña actualizada correctamente.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Contraseña actual incorrecta"
     *     )
     * )
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->passwordService->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password']
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $this->auditService->logPasswordChanged($request->user()->id);

        return response()->json(['message' => $result['message']]);
    }

    /**
     * @OA\Post(
     *     path="/api/password/force-change",
     *     tags={"Contraseñas"},
     *     summary="Cambio forzado de contraseña",
     *     description="Cambio obligatorio de contraseña en primer login",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"password", "password_confirmation"},
     *             @OA\Property(property="password", type="string", format="password", minLength=8, example="NuevaPassword123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="NuevaPassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña cambiada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contraseña establecida correctamente."),
     *             @OA\Property(property="user", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function forceChangePassword(ForceChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->passwordService->forceChangePassword(
            $request->user(),
            $validated['password']
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        $this->auditService->logPasswordChanged($request->user()->id);

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($result['user']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users/{userId}/reset-password",
     *     tags={"Contraseñas"},
     *     summary="Reset de contraseña por administrador",
     *     description="Permite a un administrador resetear la contraseña de un usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="ID del usuario",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"action"},
     *             @OA\Property(property="action", type="string", enum={"generate", "manual", "force_change_only"}, example="generate", description="generate: genera y envía por email, manual: establece password específica, force_change_only: solo marca para cambio"),
     *             @OA\Property(property="password", type="string", format="password", description="Requerido solo si action=manual"),
     *             @OA\Property(property="must_change_password", type="boolean", default=false, description="Forzar cambio en próximo login")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña reseteada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Contraseña del usuario actualizada correctamente."),
     *             @OA\Property(property="email_sent", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="No autorizado (solo admin/root)"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Usuario no encontrado"
     *     )
     * )
     */
    public function adminResetPassword(AdminResetPasswordRequest $request, string $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        // [SEGURIDAD — hallazgo fuera del pedido del cliente, hermano de C1]
        // AdminResetPasswordRequest::authorize() solo chequea la ability
        // 'users.reset_password' (root, admin_tenant); no miraba el usuario
        // OBJETIVO en ningún punto. Sin este chequeo, cualquier admin_tenant
        // podía resetear la contraseña de CUALQUIER usuario de la plataforma
        // por id arbitrario — incluidos otros admin_tenant o un root — sin
        // siquiera el chequeo débil de "comparten tenant" que tenía la vieja
        // canAccessUser(). Es toma de control de cuenta, no solo una fuga de
        // datos. Mismo patrón que UserController::update().
        //
        // Sin excepción de self-reset (a diferencia de show()): la cuenta
        // propia se administra desde /profile -> PasswordController::
        // changePassword (requiere la contraseña actual); este endpoint
        // 'admin resetea la de otro' no tiene un caso de uso legítimo sobre
        // uno mismo, y el frontend (PasswordResetModal) solo se monta desde
        // UserDetailPage sobre OTRO usuario.
        if (!$this->userService->canManageUser($request->user(), $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validated();

        $result = $this->passwordService->adminResetPassword(
            $user,
            $validated['action'],
            $validated['password'] ?? null,
            $validated['must_change_password'] ?? false
        );

        $this->auditService->logPasswordReset($user->id, [
            'by_admin' => true,
            'admin_id' => $request->user()?->id,
            'action' => $validated['action'],
        ]);

        return response()->json([
            'message' => 'Contraseña del usuario actualizada correctamente.',
            'email_sent' => $result['email_sent'],
        ]);
    }
}
