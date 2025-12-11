<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordMail;
use App\Mail\PasswordResetByAdminMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Solicitar recuperación de contraseña (forgot password)
     * POST /api/password/forgot
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Siempre responder éxito para no revelar si el email existe
        if (!$user) {
            return response()->json([
                'message' => 'Si el correo existe, recibirás un enlace de recuperación.',
            ]);
        }

        // Generar token
        $token = Str::random(64);

        // Guardar o actualizar token
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Enviar email
        Mail::to($user->email)->send(new ForgotPasswordMail($user, $token));

        return response()->json([
            'message' => 'Si el correo existe, recibirás un enlace de recuperación.',
        ]);
    }

    /**
     * Restablecer contraseña con token
     * POST /api/password/reset
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        // Buscar token
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 422);
        }

        // Verificar token
        if (!Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Token inválido o expirado.',
            ], 422);
        }

        // Verificar expiración (60 minutos)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'El enlace de recuperación ha expirado. Solicita uno nuevo.',
            ], 422);
        }

        // Actualizar contraseña
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        // Eliminar token usado
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    /**
     * Cambiar contraseña (usuario autenticado)
     * POST /api/password/change
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    /**
     * Forzar cambio de contraseña (primer login)
     * POST /api/password/force-change
     */
    public function forceChangePassword(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (!$user->must_change_password) {
            return response()->json([
                'message' => 'No es necesario cambiar la contraseña.',
            ], 400);
        }

        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Contraseña establecida correctamente.',
            'user' => $user->fresh()->load(['roles', 'tenants']),
        ]);
    }

    /**
     * Reset de contraseña por admin
     * POST /api/users/{userId}/reset-password
     */
    public function adminResetPassword(Request $request, string $userId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:generate,manual,force_change_only',
            'password' => 'required_if:action,manual|nullable|string|min:8',
            'must_change_password' => 'boolean',
        ]);

        $user = User::findOrFail($userId);
        $newPassword = null;
        $mustChangePassword = $request->input('must_change_password', false);

        switch ($request->action) {
            case 'generate':
                // Generar contraseña aleatoria
                $newPassword = Str::random(12);
                $user->update([
                    'password' => Hash::make($newPassword),
                    'must_change_password' => $mustChangePassword,
                    'password_changed_at' => now(),
                ]);
                break;

            case 'manual':
                // Usar contraseña proporcionada
                $newPassword = $request->password;
                $user->update([
                    'password' => Hash::make($newPassword),
                    'must_change_password' => $mustChangePassword,
                    'password_changed_at' => now(),
                ]);
                break;

            case 'force_change_only':
                // Solo marcar para forzar cambio
                $user->update([
                    'must_change_password' => true,
                ]);
                break;
        }

        // Enviar email de notificación
        if ($request->action !== 'force_change_only') {
            Mail::to($user->email)->send(new PasswordResetByAdminMail(
                $user,
                $newPassword,
                $mustChangePassword
            ));
        }

        return response()->json([
            'message' => 'Contraseña del usuario actualizada correctamente.',
            'email_sent' => $request->action !== 'force_change_only',
        ]);
    }
}
