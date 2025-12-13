<?php

namespace App\Services;

use App\Mail\ForgotPasswordMail;
use App\Mail\PasswordResetByAdminMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordService
{
    /**
     * Token expiration time in minutes.
     */
    protected int $tokenExpiration = 60;

    /**
     * Generate a temporary password.
     *
     * @return string
     */
    public function generateTemporaryPassword(): string
    {
        return Str::random(12);
    }

    /**
     * Request password reset (forgot password flow).
     *
     * @param string $email
     * @return bool True if email was found and token sent
     */
    public function requestPasswordReset(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return false;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $this->sendPasswordResetEmail($user, $token);

        return true;
    }

    /**
     * Reset password with token.
     *
     * @param string $email
     * @param string $token
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetPasswordWithToken(string $email, string $token, string $newPassword): array
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Token inválido o expirado.',
            ];
        }

        if (!Hash::check($token, $record->token)) {
            return [
                'success' => false,
                'message' => 'Token inválido o expirado.',
            ];
        }

        if (now()->diffInMinutes($record->created_at) > $this->tokenExpiration) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return [
                'success' => false,
                'message' => 'El enlace de recuperación ha expirado. Solicita uno nuevo.',
            ];
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ];
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return [
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ];
    }

    /**
     * Change password for authenticated user.
     *
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string]
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): array
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return [
                'success' => false,
                'message' => 'La contraseña actual es incorrecta.',
            ];
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Contraseña actualizada correctamente.',
        ];
    }

    /**
     * Force change password (first login).
     *
     * @param User $user
     * @param string $newPassword
     * @return array ['success' => bool, 'message' => string, 'user' => ?User]
     */
    public function forceChangePassword(User $user, string $newPassword): array
    {
        if (!$user->must_change_password) {
            return [
                'success' => false,
                'message' => 'No es necesario cambiar la contraseña.',
                'user' => null,
            ];
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'Contraseña establecida correctamente.',
            'user' => $user->fresh()->load(['roles', 'tenants']),
        ];
    }

    /**
     * Admin reset password for a user.
     *
     * @param User $user
     * @param string $action 'generate', 'manual', or 'force_change_only'
     * @param string|null $password Required if action is 'manual'
     * @param bool $mustChangePassword
     * @return array ['success' => bool, 'password' => ?string, 'email_sent' => bool]
     */
    public function adminResetPassword(
        User $user,
        string $action,
        ?string $password = null,
        bool $mustChangePassword = false
    ): array {
        $newPassword = null;
        $emailSent = false;

        switch ($action) {
            case 'generate':
                $newPassword = $this->generateTemporaryPassword();
                $user->update([
                    'password' => Hash::make($newPassword),
                    'must_change_password' => $mustChangePassword,
                    'password_changed_at' => now(),
                ]);
                break;

            case 'manual':
                $newPassword = $password;
                $user->update([
                    'password' => Hash::make($newPassword),
                    'must_change_password' => $mustChangePassword,
                    'password_changed_at' => now(),
                ]);
                break;

            case 'force_change_only':
                $user->update([
                    'must_change_password' => true,
                ]);
                break;
        }

        // Send notification email
        if ($action !== 'force_change_only') {
            $this->sendAdminResetEmail($user, $newPassword, $mustChangePassword);
            $emailSent = true;
        }

        return [
            'success' => true,
            'password' => $newPassword,
            'email_sent' => $emailSent,
        ];
    }

    /**
     * Send password reset email.
     *
     * @param User $user
     * @param string $token
     * @return void
     */
    public function sendPasswordResetEmail(User $user, string $token): void
    {
        try {
            Mail::to($user->email)->send(new ForgotPasswordMail($user, $token));
        } catch (\Exception $e) {
            Log::warning('[PasswordService] Failed to send password reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send admin password reset notification email.
     *
     * @param User $user
     * @param string|null $password
     * @param bool $mustChangePassword
     * @return void
     */
    public function sendAdminResetEmail(User $user, ?string $password, bool $mustChangePassword): void
    {
        try {
            Mail::to($user->email)->send(new PasswordResetByAdminMail(
                $user,
                $password,
                $mustChangePassword
            ));
        } catch (\Exception $e) {
            Log::warning('[PasswordService] Failed to send admin reset email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
