<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTenantsRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Tenants",
 *     description="API Endpoints para gestión de organizaciones (tenants)"
 * )
 */
class TenantController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/tenants",
     *     summary="Listar tenants",
     *     description="Obtiene lista de tenants. Root ve todos, admin solo sus tenants asignados",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Buscar por nombre o RUC",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrar por estado",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "suspended"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Resultados por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tenants",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Tenant")),
     *             @OA\Property(property="meta", type="object"),
     *             @OA\Property(property="links", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Tenant::query();

        // Scope: Root ve todos, admin solo sus tenants
        if (!$user->isRoot()) {
            $tenantIds = $user->tenants->pluck('id');
            $query->whereIn('id', $tenantIds);
        }

        // Búsqueda
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $tenants = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $tenants->map(function ($tenant) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'ruc' => $tenant->ruc,
                    'business_name' => $tenant->business_name,
                    'address' => $tenant->address,
                    'phone' => $tenant->phone,
                    'logo_path' => $tenant->logo_path,
                    'logo_url' => $tenant->logo_url,
                    'status' => $tenant->status,
                    'users_count' => $tenant->users()->count(),
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                ];
            }),
            'meta' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
                'from' => $tenants->firstItem(),
                'to' => $tenants->lastItem(),
            ],
            'links' => [
                'first' => $tenants->url(1),
                'last' => $tenants->url($tenants->lastPage()),
                'prev' => $tenants->previousPageUrl(),
                'next' => $tenants->nextPageUrl(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tenants",
     *     summary="Crear tenant",
     *     description="Crea un nuevo tenant. Solo accesible para root",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "ruc"},
     *             @OA\Property(property="name", type="string", example="Corporación ABC"),
     *             @OA\Property(property="ruc", type="string", example="20123456789"),
     *             @OA\Property(property="business_name", type="string", example="ABC S.A.C."),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="logo_path", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"}, default="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tenant creado exitosamente",
     *         @OA\JsonContent(ref="#/components/schemas/Tenant")
     *     ),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="Validación fallida")
     * )
     */
    public function store(StoreTenantRequest $request)
    {
        $validated = $request->validated();

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'ruc' => $validated['ruc'],
            'business_name' => $validated['business_name'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'logo_path' => $validated['logo_path'] ?? null,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Tenant creado exitosamente',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ruc' => $tenant->ruc,
                'business_name' => $tenant->business_name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'logo_path' => $tenant->logo_path,
                'logo_url' => $tenant->logo_url,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ]
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/tenants/{id}",
     *     summary="Obtener tenant",
     *     description="Obtiene detalles de un tenant específico",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del tenant",
     *         @OA\JsonContent(ref="#/components/schemas/Tenant")
     *     ),
     *     @OA\Response(response=404, description="Tenant no encontrado")
     * )
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tenant = Tenant::findOrFail($id);

        // Verificar acceso: root o usuario del tenant
        if (!$user->isRoot() && !$tenant->hasUser($user)) {
            return response()->json([
                'message' => 'No autorizado para ver este tenant'
            ], 403);
        }

        return response()->json([
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ruc' => $tenant->ruc,
                'business_name' => $tenant->business_name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'logo_path' => $tenant->logo_path,
                'logo_url' => $tenant->logo_url,
                'status' => $tenant->status,
                'users_count' => $tenant->users()->count(),
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/tenants/{id}",
     *     summary="Actualizar tenant",
     *     description="Actualiza información de un tenant",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="ruc", type="string"),
     *             @OA\Property(property="business_name", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="logo_path", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "suspended"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant actualizado exitosamente"
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Tenant no encontrado")
     * )
     */
    public function update(UpdateTenantRequest $request, $id)
    {
        $tenant = Tenant::findOrFail($id);
        $validated = $request->validated();

        $tenant->update($validated);

        return response()->json([
            'message' => 'Tenant actualizado exitosamente',
            'data' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'ruc' => $tenant->ruc,
                'business_name' => $tenant->business_name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                'logo_path' => $tenant->logo_path,
                'logo_url' => $tenant->logo_url,
                'status' => $tenant->status,
                'created_at' => $tenant->created_at,
                'updated_at' => $tenant->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/tenants/{id}",
     *     summary="Eliminar tenant",
     *     description="Elimina un tenant (soft delete). Solo accesible para root",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tenant eliminado exitosamente"
     *     ),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=404, description="Tenant no encontrado")
     * )
     */
    public function destroy(Request $request, $id)
    {
        // Solo root puede eliminar tenants
        if (!$request->user()->isRoot()) {
            return response()->json([
                'message' => 'No autorizado. Solo el administrador de plataforma puede eliminar tenants.'
            ], 403);
        }

        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return response()->json([
            'message' => 'Tenant eliminado exitosamente'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/tenants/{id}/users",
     *     summary="Obtener usuarios del tenant",
     *     description="Lista todos los usuarios asignados a un tenant",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de usuarios del tenant"
     *     )
     * )
     */
    public function users(Request $request, $id)
    {
        $user = $request->user();
        $tenant = Tenant::findOrFail($id);

        // Verificar acceso
        if (!$user->isRoot() && !$tenant->hasUser($user)) {
            return response()->json([
                'message' => 'No autorizado para ver usuarios de este tenant'
            ], 403);
        }

        $users = $tenant->users()->with('roles')->get();

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
                    'is_primary' => $u->pivot->is_primary ?? false,
                    'created_at' => $u->created_at,
                ];
            })
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/tenants/{id}/users",
     *     summary="Agregar usuario al tenant",
     *     description="Asigna un usuario existente a un tenant",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="string"),
     *             @OA\Property(property="is_primary", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario agregado al tenant exitosamente"
     *     )
     * )
     */
    public function addUser(AssignTenantsRequest $request, $id)
    {
        $validated = $request->validated();
        $tenant = Tenant::findOrFail($id);

        $userToAdd = User::findOrFail($validated['user_id']);

        // Verificar si ya está asignado
        if ($tenant->hasUser($userToAdd)) {
            return response()->json([
                'message' => 'El usuario ya está asignado a este tenant'
            ], 422);
        }

        $tenant->users()->attach($userToAdd->id, [
            'is_primary' => $validated['is_primary'] ?? false,
        ]);

        return response()->json([
            'message' => 'Usuario agregado al tenant exitosamente'
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/tenants/{id}/users/{userId}",
     *     summary="Remover usuario del tenant",
     *     description="Desasigna un usuario de un tenant",
     *     tags={"Tenants"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usuario removido del tenant exitosamente"
     *     )
     * )
     */
    public function removeUser(Request $request, $id, $userId)
    {
        $currentUser = $request->user();
        $tenant = Tenant::findOrFail($id);

        // Verificar acceso: root o admin del tenant
        if (!$currentUser->isRoot() && !$tenant->hasUser($currentUser)) {
            return response()->json([
                'message' => 'No autorizado para gestionar usuarios de este tenant'
            ], 403);
        }

        $userToRemove = User::findOrFail($userId);

        // Verificar que el usuario esté asignado
        if (!$tenant->hasUser($userToRemove)) {
            return response()->json([
                'message' => 'El usuario no está asignado a este tenant'
            ], 422);
        }

        // No permitir remover si es el tenant primario
        $pivot = $tenant->users()->where('users.id', $userId)->first()->pivot;
        if ($pivot->is_primary) {
            return response()->json([
                'message' => 'No se puede remover el tenant primario del usuario. Asigna otro tenant como primario primero.'
            ], 422);
        }

        $tenant->users()->detach($userId);

        return response()->json([
            'message' => 'Usuario removido del tenant exitosamente'
        ]);
    }
}
