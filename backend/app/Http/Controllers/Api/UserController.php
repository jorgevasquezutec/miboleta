<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UserCreationException;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateTenantSettingsRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\UserSummaryResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Usuarios",
 *     description="Gestión de usuarios del sistema"
 * )
 */
class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Listar usuarios",
     *     description="Retorna usuarios filtrados por tenant. Root ve todos, Admin ve solo de sus tenants.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="ID del tenant (opcional)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Lista de usuarios"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Query base
        $query = User::with(['roles', 'tenants', 'immediateSupervisor']);

        // Root ve todos los usuarios
        if (!$user->isRoot()) {
            // Admin y Client solo ven usuarios de sus tenants
            $tenantIds = $user->tenants->pluck('id');
            $query->whereHas('tenants', function ($q) use ($tenantIds) {
                $q->whereIn('tenants.id', $tenantIds);
            });
        }

        // Filtro por tenant específico
        if ($request->has('tenant_id')) {
            $query->whereHas('tenants', function ($q) use ($request) {
                $q->where('tenants.id', $request->tenant_id);
            });
        }

        // Búsqueda por nombre, email o documento
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('document_text', 'like', "%{$search}%");
            });
        }

        // Filtro por estado (solo si tiene valor)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Paginación
        $perPage = $request->get('per_page', 10);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'last_name' => $u->last_name,
                    'full_name' => $u->full_name,
                    'email' => $u->email,
                    'document_type' => $u->document_type,
                    'document_text' => $u->document_text,
                    'phone' => $u->phone,
                    'status' => $u->status,
                    'role' => $u->getCurrentRole(),
                    'roles' => $u->getCurrentRoles(),
                    'tenants' => $u->tenants->map(fn($t) => [
                        'id' => $t->id,
                        'name' => $t->name,
                        'ruc' => $t->ruc ?? '',
                        'is_primary' => $t->pivot->is_primary ?? false,
                    ]),
                    'immediate_supervisor' => $u->immediateSupervisor ? [
                        'id' => $u->immediateSupervisor->id,
                        'name' => $u->immediateSupervisor->name,
                        'full_name' => $u->immediateSupervisor->full_name,
                        'email' => $u->immediateSupervisor->email,
                    ] : null,
                    'created_at' => $u->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Crear usuario",
     *     description="Crea un nuevo usuario con contraseña aleatoria y envía email de bienvenida",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","role_id","tenant_id"},
     *             @OA\Property(property="name", type="string", example="Juan"),
     *             @OA\Property(property="last_name", type="string", example="Pérez"),
     *             @OA\Property(property="email", type="string", example="juan@example.com"),
     *             @OA\Property(property="document_type", type="string", example="DNI"),
     *             @OA\Property(property="document_text", type="string", example="12345678"),
     *             @OA\Property(property="phone", type="string", example="987654321"),
     *             @OA\Property(property="immediate_supervisor_id", type="integer", example=2),
     *             @OA\Property(property="role_id", type="integer", example=3),
     *             @OA\Property(property="tenant_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario creado"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $user = $this->userService->createUser(
                $request->validated(),
                $request->user()
            );

            return (new UserResource($user))
                ->additional([
                    'message' => 'Usuario creado exitosamente. Se ha enviado un correo con las credenciales.',
                    'email_sent' => true,
                ])
                ->response()
                ->setStatusCode(201);

        } catch (UserCreationException $e) {
            Log::error('[UserController] User creation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Obtener usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle del usuario"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function show(Request $request, $id)
    {
        $user = User::with(['roles', 'tenants', 'immediateSupervisor', 'subordinates'])->findOrFail($id);

        // Verificar acceso
        $currentUser = $request->user();
        if (!$this->userService->canAccessUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return new UserResource($user);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Actualizar usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Usuario actualizado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::findOrFail($id);

        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return (new UserResource($updatedUser))
            ->additional(['message' => 'Usuario actualizado exitosamente']);
    }

    /**
     * @OA\Delete(
     *     path="/api/users/{id}",
     *     tags={"Usuarios"},
     *     summary="Eliminar usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Usuario eliminado"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/subordinates",
     *     tags={"Usuarios"},
     *     summary="Obtener subordinados del usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista de subordinados")
     * )
     */
    public function subordinates($id)
    {
        $user = User::with('subordinates')->findOrFail($id);

        return UserSummaryResource::collection($user->subordinates);
    }

    /**
     * @OA\Put(
     *     path="/api/users/{id}/supervisor",
     *     tags={"Usuarios"},
     *     summary="Asignar supervisor inmediato",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="supervisor_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Supervisor asignado")
     * )
     */
    public function assignSupervisor(UpdateTenantSettingsRequest $request, $id)
    {
        $validated = $request->validated();
        $user = User::findOrFail($id);

        try {
            $updatedUser = $this->userService->assignSupervisor($user, $validated['supervisor_id']);

            return response()->json([
                'message' => 'Supervisor asignado exitosamente',
                'user' => new UserResource($updatedUser),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
