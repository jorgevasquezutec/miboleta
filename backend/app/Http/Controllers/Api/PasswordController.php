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
use App\Services\PasswordService;
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
        protected PasswordService $passwordService
    ) {
    }

    /**
     * Solicitar recuperación de contraseña (forgot password)
     * POST /api/password/forgot
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Always respond success for security (don't reveal if email exists)
        $this->passwordService->requestPasswordReset($validated['email']);

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace de recuperación.',
        ]);
    }

    /**
     * Restablecer contraseña con token
     * POST /api/password/reset
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

        return response()->json(['message' => $result['message']]);
    }

    /**
     * Cambiar contraseña (usuario autenticado)
     * POST /api/password/change
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

        return response()->json(['message' => $result['message']]);
    }

    /**
     * Forzar cambio de contraseña (primer login)
     * POST /api/password/force-change
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

        return response()->json([
            'message' => $result['message'],
            'user' => new UserResource($result['user']),
        ]);
    }

    /**
     * Reset de contraseña por admin
     * POST /api/users/{userId}/reset-password
     */
    public function adminResetPassword(AdminResetPasswordRequest $request, string $userId): JsonResponse
    {
        $validated = $request->validated();
        $user = User::findOrFail($userId);

        $result = $this->passwordService->adminResetPassword(
            $user,
            $validated['action'],
            $validated['password'] ?? null,
            $validated['must_change_password'] ?? false
        );

        return response()->json([
            'message' => 'Contraseña del usuario actualizada correctamente.',
            'email_sent' => $result['email_sent'],
        ]);
    }
}
