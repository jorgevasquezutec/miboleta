<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Autenticación",
 *     description="Endpoints para autenticación de usuarios (login, logout, refresh token)"
 * )
 */
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected AuditService $auditService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Autenticación"},
     *     summary="Iniciar sesión",
     *     description="Autenticación de usuario con email o número de documento (DNI) y password. Retorna cookies HttpOnly con access y refresh tokens.
     *
     * **Usuarios de prueba disponibles:**
     *
     * 1. **Root Admin** - root@miboleta.com / password (sin tenant, acceso total)
     * 2. **Admin ABC** - admin@corporacionabc.com / password (admin de Corporación ABC)
     * 3. **Juan Pérez** - juan.perez@corporacionabc.com / password (cliente de Corporación ABC)
     * 4. **Admin XYZ** - admin@empresaxyz.com / password (admin de Empresa XYZ)
     * 5. **Pedro López** - pedro.lopez@empresaxyz.com / password (cliente de Empresa XYZ)
     * 6. **Ana Torres** - ana.torres@email.com / password (multi-tenant: ABC + XYZ)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"login","password"},
     *             @OA\Property(property="login", type="string", example="admin@corporacionabc.com", description="Email o número de documento (DNI)"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login exitoso",
     *         @OA\JsonContent(
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="test@example.com"),
     *                 @OA\Property(property="roles", type="array", @OA\Items(type="string", example="admin"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Credenciales incorrectas",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Las credenciales proporcionadas son incorrectas."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        $result = $this->authService->attemptLogin(
            $validated['login'],
            $validated['password'],
            $request->ip(),
            $request->userAgent()
        );

        if (!$result) {
            // Log failed login attempt
            $this->auditService->logLoginFailed($validated['login'], 'Invalid credentials');

            throw ValidationException::withMessages([
                'login' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Check for specific error codes
        if (isset($result['error'])) {
            if ($result['error'] === 'user_inactive') {
                $this->auditService->logLoginFailed($validated['login'], 'User inactive');

                throw ValidationException::withMessages([
                    'login' => ['Tu cuenta se encuentra inactiva. Contacta al administrador.'],
                ]);
            }

            if ($result['error'] === 'tenant_inactive') {
                $this->auditService->logLoginFailed($validated['login'], 'Organization inactive');

                throw ValidationException::withMessages([
                    'login' => ['Tu organización se encuentra inactiva. Contacta al administrador.'],
                ]);
            }
        }

        // Log successful login - use primary tenant ID for non-root users
        $loginTenantId = $result['user']->primaryTenant()?->id;
        $this->auditService->logLogin($result['user']->id, $loginTenantId);

        return response()->json([
            'user' => $this->authService->transformAuthUser($result['user']),
        ])
            ->cookie($this->authService->createAccessTokenCookie($result['access_token']))
            ->cookie($this->authService->createRefreshTokenCookie($result['refresh_token']->token));
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     tags={"Autenticación"},
     *     summary="Refrescar access token",
     *     description="Genera un nuevo access token usando el refresh token",
     *     @OA\Response(
     *         response=200,
     *         description="Token refrescado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Refresh token inválido o expirado"
     *     )
     * )
     */
    public function refresh(Request $request)
    {
        $refreshTokenValue = $request->cookie('refresh_token');

        if (!$refreshTokenValue) {
            return response()->json([
                'message' => 'Refresh token no proporcionado',
            ], 401);
        }

        $result = $this->authService->refreshAccessToken($refreshTokenValue);

        if (!$result) {
            return response()->json([
                'message' => 'Refresh token inválido o expirado',
            ], 401);
        }

        return response()->json([
            'user' => $this->authService->transformAuthUser($result['user']),
        ])->cookie($this->authService->createAccessTokenCookie($result['access_token']));
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     tags={"Autenticación"},
     *     summary="Obtener usuario autenticado",
     *     description="Retorna la información del usuario actual",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Usuario autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="test@example.com"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string", example="admin"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load(['roles', 'tenants']);

        return response()->json($this->authService->transformAuthUser($user));
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     tags={"Autenticación"},
     *     summary="Cerrar sesión",
     *     description="Invalida todos los tokens del usuario y limpia las cookies",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Sesión cerrada correctamente")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autenticado"
     *     )
     * )
     */
    public function logout(Request $request)
    {
        // Log logout
        $this->auditService->logLogout();

        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ])
            ->cookie($this->authService->createExpiredCookie('access_token'))
            ->cookie($this->authService->createExpiredCookie('refresh_token'));
    }
}
