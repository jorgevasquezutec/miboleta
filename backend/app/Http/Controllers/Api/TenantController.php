<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTenantsRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Tenants",
 *     description="API Endpoints para gestión de organizaciones (tenants)"
 * )
 */
class TenantController extends Controller
{
    public function __construct(
        protected TenantService $tenantService
    ) {
    }

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
        $filters = [
            'search' => $request->search,
            'status' => $request->status,
            'per_page' => $request->get('per_page', 10),
        ];

        $tenants = $this->tenantService->getTenants($request->user(), $filters);

        return response()->json([
            'data' => $tenants->map(fn($t) => $this->tenantService->transformTenantForList($t)),
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
        $tenant = $this->tenantService->createTenant($request->validated());

        return response()->json([
            'message' => 'Tenant creado exitosamente',
            'data' => new TenantResource($tenant)
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
        try {
            $tenant = $this->tenantService->getTenant($id, $request->user());

            return response()->json([
                'data' => $this->tenantService->transformTenantForList($tenant)
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
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
        $updatedTenant = $this->tenantService->updateTenant($tenant, $request->validated());

        return response()->json([
            'message' => 'Tenant actualizado exitosamente',
            'data' => new TenantResource($updatedTenant)
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
        try {
            $this->tenantService->deleteTenant($id, $request->user());

            return response()->json([
                'message' => 'Tenant eliminado exitosamente'
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
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
        try {
            $users = $this->tenantService->getTenantUsers($id, $request->user());

            return response()->json([
                'data' => $users->map(fn($u) => $this->tenantService->transformUserForTenant($u))
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
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

        $result = $this->tenantService->addUserToTenant(
            $id,
            $validated['user_id'],
            $validated['is_primary'] ?? false
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => $result['message']]);
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
        try {
            $result = $this->tenantService->removeUserFromTenant($id, $userId, $request->user());

            if (!$result['success']) {
                return response()->json(['message' => $result['message']], 422);
            }

            return response()->json(['message' => $result['message']]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/tenants/{id}/smtp/test",
     *     summary="Probar conexión SMTP del tenant",
     *     description="Intenta abrir y cerrar una conexión con el servidor SMTP propio configurado para la empresa (ítem 24-menor). Solo accesible para root.",
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
     *         description="Resultado de la prueba de conexión",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=404, description="Tenant no encontrado")
     * )
     */
    public function testSmtpConnection(Request $request, $id)
    {
        try {
            $result = $this->tenantService->testMailerConnection($id, $request->user());

            return response()->json($result);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        }
    }
}
