<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
     * Duración del "boleto de vuelta" del root (cookie impersonator_return,
     * ver impersonate()/leaveImpersonation()). Mucho más corta que
     * REFRESH_TOKEN_EXPIRY a propósito: solo tiene que sobrevivir una sesión
     * de trabajo, no un mes — si se filtra, la ventana de riesgo es de horas.
     */
    public const IMPERSONATOR_RETURN_EXPIRY = 60 * 8; // 8 horas, en minutos

    /**
     * Prefijo con el que se marca el `name` del token Sanctum de una sesión
     * impersonada ("impersonation:{rootId}"). Es el ÚNICO canal donde vive la
     * marca — hoy nadie más inspecciona `name` ni `abilities` en el backend,
     * así que es libre y retrocompatible (no se toca la columna `abilities`,
     * sigue siendo ['*'] para no alterar autorización). Constante pública y
     * el parser estático porque AuditService (captura central del
     * impersonator en log()) necesita leer la misma marca sin depender de una
     * instancia de este servicio.
     */
    public const IMPERSONATION_TOKEN_PREFIX = 'impersonation:';

    /**
     * Extrae el id del root de un `name` de token marcado como impersonación,
     * o null si el token no está marcado (sesión normal).
     */
    public static function impersonatorIdFromTokenName(?string $tokenName): ?int
    {
        if ($tokenName === null || !str_starts_with($tokenName, self::IMPERSONATION_TOKEN_PREFIX)) {
            return null;
        }

        $id = substr($tokenName, strlen(self::IMPERSONATION_TOKEN_PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }

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
        // isRoot() en vez de getCurrentRole() !== 'root': determinístico y sin
        // depender del respaldo global de roles.
        if (!$user->isRoot()) {
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
     * Propaga la marca de impersonation al token nuevo leyéndola de la FILA
     * del refresh token (refresh_tokens.impersonator_id), no de la cookie
     * access_token: esa cookie caduca en el mismo minuto que el token que
     * contiene, así que en el refresh real de un navegador ya no llega.
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

        // Usuario eliminado (soft delete): la relación belongsTo aplica el
        // scope global de SoftDeletes, así que devuelve null aunque la fila de
        // refresh_tokens siga ahí. Sin esta guarda, el `$user->status` de
        // abajo reventaba con "property on null" y el endpoint respondía 500
        // en vez de un 401 limpio, en bucle hasta que el token expirara.
        // Se revocan TODOS los refresh tokens de ese user_id (la columna sí
        // está en la fila) y no solo el presentado: si el usuario fue
        // eliminado antes de que destroy() revocara sesiones, puede tener
        // varios vivos y volveríamos aquí con cada uno.
        if (!$user) {
            RefreshToken::revokeAllForUser($refreshToken->user_id);

            return null;
        }

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
        // isRoot() en vez de getCurrentRole() !== 'root': determinístico.
        if (!$user->isRoot()) {
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

        // ¿Esta sesión es una impersonación? Se resuelve desde la FILA del
        // refresh token presentado, no desde la cookie access_token: esa
        // cookie caduca el mismo minuto que el token que contiene, así que en
        // el refresh real de un navegador NO llega. Atarse a ella hacía que
        // cada refresh perdiera la marca —sesión viva pero auditoría ciega al
        // root— y cayera en el tokens()->delete() de abajo, matando además la
        // sesión real del empleado.
        $impersonatorId = $refreshToken->impersonator_id;

        if ($impersonatorId !== null) {
            $newTokenName = self::IMPERSONATION_TOKEN_PREFIX . $impersonatorId;

            // Revocar SOLO los access tokens de ESTA impersonación (los que
            // llevan la marca de este root). $user->tokens()->delete() se
            // llevaría por delante la sesión REAL del empleado, que
            // impersonate() dejó intacta a propósito.
            $user->tokens()->where('name', $newTokenName)->delete();
        } else {
            // Comportamiento existente: revoca TODOS los access tokens del user.
            $user->tokens()->delete();
            $newTokenName = 'access_token';
        }

        // Create new access token
        $newToken = $user->createToken($newTokenName, ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY));
        $user->withAccessToken($newToken->accessToken);

        return [
            'user' => $user,
            'access_token' => $newToken->plainTextToken,
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
     * Emite una sesión "iniciar sesión como" $target: root pasa a operar con
     * la identidad del empleado (mismo user_id en toda la lógica de negocio),
     * dejando la marca de quién está detrás en el `name` del token Sanctum
     * (ver IMPERSONATION_TOKEN_PREFIX) para que AuditService::log() la
     * capture en cada acción posterior.
     *
     * Deliberadamente NO reutiliza attemptLogin(): ese hace
     * $user->tokens()->delete() y borraría la sesión REAL de $target en su
     * propio navegador. Aquí se AGREGA un token nuevo sin tocar los suyos, y
     * sin actualizar su last_login_at (root entrando no es "el empleado
     * inició sesión").
     */
    public function impersonate(User $root, User $target, ?string $ip = null, ?string $userAgent = null): array
    {
        $accessToken = $target->createToken(
            self::IMPERSONATION_TOKEN_PREFIX . $root->id,
            ['*'], // Sin cambios: la autorización sigue siendo la del empleado.
            now()->addMinutes(self::ACCESS_TOKEN_EXPIRY)
        );
        // Para que transformAuthUser($target) refleje la marca en la misma
        // respuesta (currentAccessToken() no se resuelve solo por crear el
        // token; hay que asociarlo al modelo explícitamente).
        $target->withAccessToken($accessToken->accessToken);

        // Refresh token NUEVO de $target (no se tocan los suyos existentes),
        // marcado con el root: es lo que permite que refreshAccessToken()
        // reconozca la sesión impersonada sin la cookie access_token, que a
        // esa altura el navegador ya descartó (ver la migración
        // 2026_08_18_000002).
        $refreshToken = RefreshToken::generate($target, $ip, $userAgent, $root->id);

        // "Boleto de vuelta": el refresh token del ROOT para restaurar su
        // sesión real al salir (ver leaveImpersonation()). TTL propio y
        // corto (IMPERSONATOR_RETURN_EXPIRY), no el de 30 días de
        // RefreshToken::generate().
        $impersonatorReturnToken = RefreshToken::create([
            'user_id' => $root->id,
            'token' => hash('sha256', Str::random(60)),
            'expires_at' => Carbon::now()->addMinutes(self::IMPERSONATOR_RETURN_EXPIRY),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);

        return [
            'user' => $target,
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $refreshToken,
            'impersonator_return_token' => $impersonatorReturnToken,
        ];
    }

    /**
     * Cierra una sesión impersonada y restaura la sesión real de root.
     * Espejo de impersonate(): revoca SOLO lo que se creó al entrar (el
     * access token de impersonación y el refresh token nuevo del empleado),
     * nunca las sesiones reales de nadie.
     *
     * $impersonatedUser es el usuario autenticado de la request actual (el
     * empleado, vía el token marcado); $returnTokenValue e
     * $employeeRefreshTokenValue son los valores CRUDOS de las cookies
     * impersonator_return y refresh_token respectivamente.
     *
     * Devuelve null si no hay una impersonación activa que cerrar: el token
     * actual no está marcado, o el "boleto de vuelta" falta, no es válido, o
     * no corresponde al root de la marca (ver RefreshToken::isValid()).
     */
    public function leaveImpersonation(
        User $impersonatedUser,
        ?string $returnTokenValue,
        ?string $employeeRefreshTokenValue,
        ?string $ip = null,
        ?string $userAgent = null
    ): ?array {
        $currentToken = $impersonatedUser->currentAccessToken();
        // "?->name ?? null": currentAccessToken() puede devolver un
        // TransientToken (auth por guard de sesión, sin token real) en vez de
        // un PersonalAccessToken; ese objeto no tiene `name` y leerlo directo
        // revienta con 500 en vez de tratarse como "sesión sin marcar".
        $rootId = self::impersonatorIdFromTokenName($currentToken?->name ?? null);

        if ($rootId === null || !$returnTokenValue) {
            return null;
        }

        $returnToken = RefreshToken::where('token', $returnTokenValue)
            ->where('user_id', $rootId)
            ->first();

        if (!$returnToken || !$returnToken->isValid()) {
            return null;
        }

        $root = $returnToken->user;

        if (!$root) {
            return null;
        }

        // Mismo corte que attemptLogin() y refreshAccessToken(): una cuenta
        // que dejó de estar activa no recupera sesión. Sin esto, a un root
        // desactivado MIENTRAS impersonaba se le devolvía al salir un access
        // token válido de 60 minutos — la desactivación no surtía efecto hasta
        // el refresh siguiente.
        if ($root->status !== 'active') {
            return null;
        }

        // Revocar SOLO lo que pertenece a ESTA sesión de impersonación: el
        // access token marcado y el refresh token que se creó para el
        // empleado al entrar — nunca sus tokens reales, que ni se tocaron en
        // impersonate().
        $currentToken->delete();
        $returnToken->revoke();

        if ($employeeRefreshTokenValue) {
            RefreshToken::where('token', $employeeRefreshTokenValue)
                ->where('user_id', $impersonatedUser->id)
                ->first()
                ?->revoke();
        }

        $accessToken = $root->createToken('access_token', ['*'], now()->addMinutes(self::ACCESS_TOKEN_EXPIRY));
        $root->withAccessToken($accessToken->accessToken);

        $rootRefreshToken = RefreshToken::generate($root, $ip, $userAgent);

        return [
            'user' => $root,
            'access_token' => $accessToken->plainTextToken,
            'refresh_token' => $rootRefreshToken,
        ];
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
            // Root detrás de la sesión, si $user está siendo impersonado (ver
            // impersonate()/currentAccessToken()->name). null en el caso
            // normal. Al mezclarse en /me (que aplana este arreglo en la raíz
            // de la respuesta, ver AuthController::me) queda expuesto tal
            // cual pide el frontend para pintar el banner "Estás como...".
            'impersonator' => $this->resolveImpersonator($user),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Resumen del root que está detrás de $user si su sesión actual está
     * impersonada, o null. Requiere que $user tenga el access token asociado
     * (currentAccessToken() no null) — ya lo hace Sanctum al autenticar por
     * request, y AuthService lo asocia a mano tras crear un token nuevo (ver
     * impersonate()/leaveImpersonation()/refreshAccessToken()).
     */
    private function resolveImpersonator(User $user): ?array
    {
        // "?->name ?? null" y no solo "?->name": currentAccessToken() puede
        // devolver un TransientToken (auth por guard de sesión, p. ej.
        // actingAs() en tests) en vez de un PersonalAccessToken real; ese
        // objeto no tiene `name` y leerlo directo revienta con 500 en vez de
        // tratarse, correctamente, como "sesión sin impersonar".
        $rootId = self::impersonatorIdFromTokenName($user->currentAccessToken()?->name ?? null);

        if ($rootId === null) {
            return null;
        }

        $root = User::find($rootId);

        if (!$root) {
            return null;
        }

        return [
            'id' => $root->id,
            'full_name' => $root->full_name,
            'email' => $root->email,
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
     * Create impersonator return cookie ("boleto de vuelta" del root — ver
     * AuthService::impersonate()). Mismos atributos que refresh_token
     * (HttpOnly, SameSite=Strict) salvo el TTL, mucho más corto
     * (IMPERSONATOR_RETURN_EXPIRY) porque solo debe sobrevivir la sesión de
     * trabajo, no 30 días.
     *
     * @param string $token
     * @return \Symfony\Component\HttpFoundation\Cookie
     */
    public function createImpersonatorReturnCookie(string $token)
    {
        return cookie(
            'impersonator_return',
            $token,
            self::IMPERSONATOR_RETURN_EXPIRY,
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
