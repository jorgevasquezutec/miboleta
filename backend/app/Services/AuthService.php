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
     * @param string $email
     * @param string $password
     * @return array|null ['user' => User, 'access_token' => string, 'refresh_token' => RefreshToken] or null if failed
     */
    public function attemptLogin(string $email, string $password, ?string $ip = null, ?string $userAgent = null): ?array
    {
        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        // Delete previous tokens
        $user->tokens()->delete();

        // Create access token
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY))->plainTextToken;

        // Create refresh token
        $refreshToken = RefreshToken::generate($user, $ip, $userAgent);

        // Update last login
        $user->update(['last_login_at' => Carbon::now()]);

        // Load relationships
        $user->load(['roles', 'tenants']);

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

        // Delete previous access tokens
        $user->tokens()->delete();

        // Create new access token
        $accessToken = $user->createToken('access_token', ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY))->plainTextToken;

        // Load relationships
        $user->load(['roles', 'tenants']);

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
            'status' => $user->status,
            'must_change_password' => $user->must_change_password,
            'role' => $user->getCurrentRole(),
            'roles' => $user->getCurrentRoles(),
            'tenants' => $user->tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ruc' => $tenant->ruc,
                    'logo_url' => $tenant->logo_url,
                    'is_primary' => $tenant->pivot->is_primary,
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
