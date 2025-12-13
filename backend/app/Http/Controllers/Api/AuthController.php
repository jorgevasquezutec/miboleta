<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     tags={"Autenticación"},
     *     summary="Iniciar sesión",
     *     description="Autenticación de usuario con email y password. Retorna cookies HttpOnly con access y refresh tokens.
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
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@corporacionabc.com"),
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
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Eliminar tokens anteriores del usuario
        $user->tokens()->delete();

        // Crear access token (duración: 1 hora)
        $accessToken = $user->createToken('access_token', ['*'], now()->addHour())->plainTextToken;

        // Crear refresh token en BD (duración: 30 días)
        $refreshToken = RefreshToken::generate(
            $user,
            $request->ip(),
            $request->userAgent()
        );

        // Actualizar último login
        $user->update(['last_login_at' => Carbon::now()]);

        // Cargar relaciones
        $user->load(['roles', 'tenants']);

        // Crear respuesta con cookies HttpOnly
        return response()->json([
            'user' => [
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
            ],
        ])->cookie(
                'access_token',
                $accessToken,
                60, // 1 hora en minutos
                '/',
                null,
                false, // secure - false en desarrollo, true en producción
                true, // httpOnly
                false,
                'Lax'
            )->cookie(
                'refresh_token',
                $refreshToken->token,
                60 * 24 * 30, // 30 días en minutos
                '/',
                null,
                false, // secure - false en desarrollo, true en producción
                true, // httpOnly
                false,
                'Strict'
            );
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

        // Buscar refresh token en BD
        $refreshToken = RefreshToken::where('token', $refreshTokenValue)->first();

        if (!$refreshToken || !$refreshToken->isValid()) {
            return response()->json([
                'message' => 'Refresh token inválido o expirado',
            ], 401);
        }

        // Actualizar último uso
        $refreshToken->updateLastUsed();

        // Obtener usuario
        $user = $refreshToken->user;

        // Eliminar access tokens anteriores
        $user->tokens()->delete();

        // Crear nuevo access token (1 hora)
        $accessToken = $user->createToken('access_token', ['*'], now()->addHour())->plainTextToken;

        // Cargar relaciones
        $user->load(['roles', 'tenants']);

        return response()->json([
            'user' => [
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
            ],
        ])->cookie(
                'access_token',
                $accessToken,
                60, // 1 hora
                '/',
                null,
                false, // secure - false en desarrollo
                true,
                false,
                'Lax'
            );
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

        return response()->json([
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
        ]);
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
        $user = $request->user();

        // Eliminar todos los access tokens
        $user->tokens()->delete();

        // Revocar todos los refresh tokens
        RefreshToken::revokeAllForUser($user->id);

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ])->cookie(
                'access_token',
                '',
                -1, // Expirar inmediatamente
                '/',
                null,
                false, // secure - false en desarrollo
                true,
                false,
                'Lax'
            )->cookie(
                'refresh_token',
                '',
                -1, // Expirar inmediatamente
                '/',
                null,
                false, // secure - false en desarrollo
                true,
                false,
                'Strict'
            );
    }
}

