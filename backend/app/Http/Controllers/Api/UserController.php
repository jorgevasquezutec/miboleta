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
        $query = User::with(['roles', 'tenants']);

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
            'data' => $users->map(function ($u) use ($user) {
                // 🔒 SECURITY: Filter tenants to only show those the current admin has access to
                $visibleTenants = $u->tenants;

                if (!$user->isRoot()) {
                    // Non-root users can only see tenants they have access to
                    $allowedTenantIds = $user->tenants->pluck('id')->toArray();
                    $visibleTenants = $u->tenants->filter(function ($t) use ($allowedTenantIds) {
                        return in_array($t->id, $allowedTenantIds);
                    });
                }

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
                    'tenants' => $visibleTenants->map(function($t) {
                        $supervisorId = $t->pivot->supervisor_id ?? null;
                        $supervisor = null;
                        
                        // Cargar información del supervisor si existe
                        if ($supervisorId) {
                            $supervisorUser = \App\Models\User::find($supervisorId);
                            if ($supervisorUser) {
                                $supervisor = [
                                    'id' => $supervisorUser->id,
                                    'name' => $supervisorUser->name,
                                    'full_name' => $supervisorUser->full_name,
                                    'email' => $supervisorUser->email,
                                ];
                            }
                        }
                        
                        return [
                            'id' => $t->id,
                            'name' => $t->name,
                            'ruc' => $t->ruc ?? '',
                            'is_primary' => $t->pivot->is_primary ?? false,
                            'supervisor_id' => $supervisorId,
                            'supervisor' => $supervisor,
                        ];
                    })->values(),  // ✅ Reset array keys after filter
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

    // ... (store method is fine, uses service)

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
        $user = User::with(['roles', 'tenants'])->findOrFail($id);

        // Verificar acceso
        $currentUser = $request->user();
        if (!$this->userService->canAccessUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return new UserResource($user);
    }

    /**
     * @OA\Post(
     *     path="/api/users",
     *     tags={"Usuarios"},
     *     summary="Crear usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Usuario creado"),
     *     @OA\Response(response=422, description="Errores de validación")
     * )
     */
    public function store(StoreUserRequest $request)
    {
        try {
            $user = $this->userService->createUser($request->validated());
            return response()->json([
                'message' => 'Usuario creado exitosamente',
                'data' => new UserResource($user),
            ], 201);
        } catch (UserCreationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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

        // Verificar acceso
        $currentUser = $request->user();
        if (!$this->userService->canAccessUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $updatedUser = $this->userService->updateUser($user, $request->validated());

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => new UserResource($updatedUser),
        ]);
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
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Verificar acceso
        $currentUser = $request->user();
        if (!$this->userService->canAccessUser($currentUser, $user)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/users/subordinates",
     *     tags={"Usuarios"},
     *     summary="Obtener subordinados del usuario actual o de un usuario específico",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="ID del tenant (opcional, filtra por tenant específico)",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Lista de subordinados")
     * )
     */
    public function subordinates(Request $request, $id = null)
    {
        // Si se proporciona un ID, obtener ese usuario, sino usar el autenticado
        if ($id) {
            $user = User::findOrFail($id);

            // Verificar acceso
            $currentUser = $request->user();
            if (!$this->userService->canAccessUser($currentUser, $user)) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
        } else {
            $user = $request->user();
        }

        $tenantId = $request->query('tenant_id');

        if ($tenantId) {
            // Obtener subordinados de un tenant específico
            $subordinates = $user->subordinatesForTenant((int) $tenantId)->get();
        } else {
            // Obtener todos los subordinados (de todos los tenants)
            $subordinates = $user->subordinates()->get();
        }

        return response()->json([
            'data' => $subordinates->map(function ($subordinate) {
                return [
                    'id' => $subordinate->id,
                    'name' => $subordinate->name,
                    'last_name' => $subordinate->last_name,
                    'full_name' => $subordinate->full_name,
                    'email' => $subordinate->email,
                    'status' => $subordinate->status,
                    'role' => $subordinate->getCurrentRole(),
                    'roles' => $subordinate->getCurrentRoles(),
                    'tenant_id' => $subordinate->pivot->tenant_id ?? null,
                    'is_primary' => $subordinate->pivot->is_primary ?? false,
                ];
            }),
        ]);
    }
}
