<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * Access token expiration in minutes.
     */
    public const ACCESS_TOKEN_EXPIRY = 60; // 1 hour

    /**
     * Refresh token expiration in minutes.
     */
    public const REFRESH_TOKEN_EXPIRY = 60 * 24 * 30; // 30 days

    /**
     * Attempt to authenticate user.
     *
     * Acepta como identificador el correo electrónico o el número de
     * documento (DNI): si $login tiene formato de email se busca por
     * 'email', de lo contrario se busca por 'document_text'.
     *
     * @param string $login Email o DNI (document_text)
     * @param string $password
     * @return array|null ['user' => User, 'access_token' => string, 'refresh_token' => RefreshToken] or null if failed
     */
    public function attemptLogin(string $login, string $password, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $user = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $login)->first()
            : User::where('document_text', $login)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        // Check if user is active
        if ($user->status !== 'active') {
            return ['error' => 'user_inactive'];
        }

        // Load tenants to check status
        $user->load(['roles', 'tenants']);

        // Check if user has access to at least one active tenant (skip for root users)
        if ($user->getCurrentRole() !== 'root') {
            $hasActiveTenant = $user->tenants->contains(function ($tenant) {
                return $tenant->status === 'active';
            });

            if (!$hasActiveTenant && $user->tenants->isNotEmpty()) {
                // All tenants are inactive - return specific error
                return ['error' => 'tenant_inactive'];
            }
        }

        // Delete previous tokens
        $user->tokens()->delete();

        // Create access token
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY))->plainTextToken;

        // Create refresh token
        $refreshToken = RefreshToken::generate($user, $ip, $userAgent);

        // Update last login
        $user->update(['last_login_at' => Carbon::now()]);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param string $refreshTokenValue
     * @return array|null ['user' => User, 'access_token' => string] or null if invalid
     */
    public function refreshAccessToken(string $refreshTokenValue): ?array
    {
        $refreshToken = RefreshToken::where('token', $refreshTokenValue)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return null;
        }

        // Update last used
        $refreshToken->updateLastUsed();

        $user = $refreshToken->user;

        // Check if user is still active
        if ($user->status !== 'active') {
            // User is now inactive - revoke session
            $user->tokens()->delete();
            RefreshToken::revokeAllForUser($user->id);
            return null;
        }

        // Load relationships
        $user->load(['roles', 'tenants']);

        // Check if user still has access to at least one active tenant (skip for root users)
        if ($user->getCurrentRole() !== 'root') {
            $hasActiveTenant = $user->tenants->contains(function ($tenant) {
                return $tenant->status === 'active';
            });

            if (!$hasActiveTenant && $user->tenants->isNotEmpty()) {
                // All tenants are now inactive - revoke session
                $user->tokens()->delete();
                RefreshToken::revokeAllForUser($user->id);
                return null;
            }
        }

        // Delete previous access tokens
        $user->tokens()->delete();

        // Create new access token
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY))->plainTextToken;

        return [
            'user' => $user,
            'access_token' => $accessToken,
        ];
    }

    /**
     * Logout user by revoking all tokens.
     *
     * @param User $user
     * @return void
     */
    public function logout(User $user): void
    {
        // Delete all access tokens
        $user->tokens()->delete();

        // Revoke all refresh tokens
        RefreshToken::revokeAllForUser($user->id);
    }

    /**
     * Transform user for auth response.
     *
     * @param User $user
     * @return array
     */
    public function transformAuthUser(User $user): array
    {
        // Cargar de una sola vez los roles por empresa (con su Role) para
        // evitar N+1 queries al armar el arreglo 'tenants' de más abajo.
        $user->loadMissing('tenantRoles.role');
        $tenantRolesByTenant = $user->tenantRoles->groupBy('tenant_id');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'document_type' => $user->document_type,
            'document_text' => $user->document_text,
            'phone' => $user->phone,
            // Fecha de nacimiento (ítem 37): formateada a 'Y-m-d' porque el
            // modelo la castea a Carbon (ver User::casts). Se serializa así
            // en vez de dejar que Carbon use su formato ISO por defecto,
            // para que el frontend (formatDate) reciba siempre 'YYYY-MM-DD'.
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'status' => $user->status,
            'must_change_password' => $user->must_change_password,
            // Rol "global" de respaldo (ver User::getCurrentRole /
            // UserService::syncGlobalRoleFallback). Para el rol específico
            // por empresa, el frontend debe usar tenants[].roles /
            // tenants[].role de más abajo (base para el selector de rol
            // activo por empresa).
            'role' => $user->getCurrentRole(),
            'roles' => $user->getCurrentRoles(),
            'tenants' => $user->tenants->map(function ($tenant) use ($tenantRolesByTenant) {
                $tenantRoleNames = ($tenantRolesByTenant->get($tenant->id) ?? collect())
                    ->pluck('role.name')
                    ->filter()
                    ->values();

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ruc' => $tenant->ruc,
                    'logo_url' => $tenant->logo_url,
                    'is_primary' => $tenant->pivot->is_primary,
                    'supervisor_id' => $tenant->pivot->supervisor_id,
                    // Roles operativos del usuario en esta empresa específica
                    // (user_tenant_roles) y el de mayor prioridad entre ellos
                    // (ver User::ROLE_PRIORITY): base para el selector de rol
                    // activo por empresa en el frontend.
                    'roles' => $tenantRoleNames,
                    'role' => User::highestPriorityRole($tenantRoleNames),
                ];
            }),
            'primary_tenant' => $user->primaryTenant() ? [
                'id' => $user->primaryTenant()->id,
                'name' => $user->primaryTenant()->name,
                'ruc' => $user->primaryTenant()->ruc,
            ] : null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Create access token cookie.
     *
     * @param string $token
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function createAccessTokenCookie(string $token)
    {
        return cookie(
            'access_token',
            $token,
            self::ACCESS_TOKEN_EXPIRY,
            '/',
            null,
            false, // secure - false in development
            true,  // httpOnly
            false,
            'Lax'
        );
    }

    /**
     * Create refresh token cookie.
     *
     * @param string $token
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function createRefreshTokenCookie(string $token)
    {
        return cookie(
            'refresh_token',
            $token,
            self::REFRESH_TOKEN_EXPIRY,
            '/',
            null,
            false, // secure - false in development
            true,  // httpOnly
            false,
            'Strict'
        );
    }

    /**
     * Create expired cookie (for logout).
     *
     * @param string $name
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function createExpiredCookie(string $name)
    {
        return cookie(
            $name,
            '',
            -1, // Expire immediately
            '/',
            null,
            false,
            true,
            false,
            $name === 'access_token' ? 'Lax' : 'Strict'
        );
    }
}
