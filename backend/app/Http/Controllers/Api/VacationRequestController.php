<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVacationRequestRequest;
use App\Http\Requests\RejectVacationRequestRequest;
use App\Http\Resources\VacationRequestResource;
use App\Models\VacationRequest;
use App\Services\VacationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Vacaciones",
 *     description="Gestión de solicitudes de vacaciones"
 * )
 */
class VacationRequestController extends Controller
{
    public function __construct(
        protected VacationService $vacationService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests",
     *     tags={"Vacaciones"},
     *     summary="Listar solicitudes de vacaciones",
     *     description="Lista las solicitudes según el rol del usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", description="Filtrar por estado", @OA\Schema(type="string")),
     *     @OA\Parameter(name="year", in="query", description="Filtrar por año", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", description="Resultados por página", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lista de solicitudes")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $role = $user->getCurrentRole();

        // Obtener tenant IDs del middleware o usar tenants del usuario
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'status' => $request->status,
            'year' => $request->year,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'search' => $request->search,
            'per_page' => $request->get('per_page', 15),
        ];

        // By default, everyone sees their own requests ("Mis Vacaciones")
        // Admin can see all tenant requests when scope=tenant (used in History page)
        $scope = $request->get('scope', 'mine');

        if ($scope === 'tenant' && in_array($role, ['root', 'admin'])) {
            $requests = $this->vacationService->getAllRequests($user, $filters);
        } else {
            $requests = $this->vacationService->getRequestsForUser($user, $filters);
        }

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/vacation-requests",
     *     tags={"Vacaciones"},
     *     summary="Crear solicitud de vacaciones",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_date", "end_date", "days_requested"},
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="days_requested", type="number"),
     *             @OA\Property(property="reason", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Solicitud creada"),
     *     @OA\Response(response=400, description="Error de validación")
     * )
     */
    public function store(CreateVacationRequestRequest $request): JsonResponse
    {
        $user = Auth::user();

        // El tenant_id viene en el request validado desde el frontend
        $tenantId = $request->validated()['tenant_id'] ?? $user->tenants->first()?->id;

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant no especificado'], 400);
        }

        try {
            $vacationRequest = $this->vacationService->createRequest(
                $request->validated(),
                $user,
                $tenantId
            );

            return response()->json([
                'message' => 'Solicitud de vacaciones creada correctamente',
                'data' => new VacationRequestResource($vacationRequest),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/{id}",
     *     tags={"Vacaciones"},
     *     summary="Ver detalle de solicitud",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detalle de la solicitud"),
     *     @OA\Response(response=404, description="No encontrada")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::with([
            'user',
            'approvedByUser',
            'rejectedByUser',
            'confirmedByUser'
        ])->findOrFail($id);

        // Check access
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client' && $vacationRequest->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'data' => new VacationRequestResource($vacationRequest),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/vacation-requests/{id}",
     *     tags={"Vacaciones"},
     *     summary="Cancelar solicitud",
     *     description="El empleado cancela su propia solicitud pendiente",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Solicitud cancelada"),
     *     @OA\Response(response=400, description="No se puede cancelar")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::findOrFail($id);

        try {
            $this->vacationService->cancelRequest($vacationRequest, Auth::user());

            return response()->json([
                'message' => 'Solicitud cancelada correctamente',
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/vacation-requests/{id}/approve",
     *     tags={"Vacaciones"},
     *     summary="Aprobar solicitud",
     *     description="Solo el supervisor puede aprobar",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Solicitud aprobada"),
     *     @OA\Response(response=403, description="No autorizado")
     * )
     */
    public function approve(int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::findOrFail($id);

        try {
            $updated = $this->vacationService->approveRequest($vacationRequest, Auth::user());

            return response()->json([
                'message' => 'Solicitud aprobada correctamente',
                'data' => new VacationRequestResource($updated),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/vacation-requests/{id}/reject",
     *     tags={"Vacaciones"},
     *     summary="Rechazar solicitud",
     *     description="Solo el supervisor puede rechazar. Requiere motivo.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(@OA\Property(property="reason", type="string"))
     *     ),
     *     @OA\Response(response=200, description="Solicitud rechazada"),
     *     @OA\Response(response=403, description="No autorizado")
     * )
     */
    public function reject(RejectVacationRequestRequest $request, int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::findOrFail($id);

        try {
            $updated = $this->vacationService->rejectRequest(
                $vacationRequest,
                Auth::user(),
                $request->reason
            );

            return response()->json([
                'message' => 'Solicitud rechazada',
                'data' => new VacationRequestResource($updated),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/vacation-requests/{id}/mark-taken",
     *     tags={"Vacaciones"},
     *     summary="Marcar vacaciones como tomadas",
     *     description="El supervisor confirma que el empleado tomó sus vacaciones",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Confirmado como tomadas")
     * )
     */
    public function markTaken(int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::findOrFail($id);

        try {
            $updated = $this->vacationService->markAsTaken($vacationRequest, Auth::user());

            return response()->json([
                'message' => 'Vacaciones marcadas como tomadas',
                'data' => new VacationRequestResource($updated),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/vacation-requests/{id}/mark-not-taken",
     *     tags={"Vacaciones"},
     *     summary="Marcar vacaciones como NO tomadas",
     *     description="El supervisor confirma que el empleado NO tomó sus vacaciones",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Confirmado como NO tomadas")
     * )
     */
    public function markNotTaken(int $id): JsonResponse
    {
        $vacationRequest = VacationRequest::findOrFail($id);

        try {
            $updated = $this->vacationService->markAsNotTaken($vacationRequest, Auth::user());

            return response()->json([
                'message' => 'Vacaciones marcadas como NO tomadas',
                'data' => new VacationRequestResource($updated),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/pending-approval",
     *     tags={"Vacaciones"},
     *     summary="Solicitudes pendientes de aprobar",
     *     description="Lista solicitudes de subordinados pendientes de aprobación",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lista de solicitudes pendientes")
     * )
     */
    public function pendingApprovals(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del middleware
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'per_page' => $request->get('per_page', 15),
        ];

        $requests = $this->vacationService->getPendingApprovals($user, $filters);

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/pending-confirmation",
     *     tags={"Vacaciones"},
     *     summary="Vacaciones pendientes de confirmar",
     *     description="Lista vacaciones aprobadas que ya pasaron y faltan confirmar si fueron tomadas",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lista de vacaciones pendientes de confirmar")
     * )
     */
    public function pendingConfirmations(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del middleware
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'per_page' => $request->get('per_page', 15),
        ];

        $requests = $this->vacationService->getPendingConfirmations($user, $filters);

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/my-team",
     *     tags={"Vacaciones"},
     *     summary="Vacaciones del equipo",
     *     description="Lista todas las solicitudes del equipo del supervisor",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lista de vacaciones del equipo")
     * )
     */
    public function myTeam(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del middleware
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'status' => $request->status,
            'year' => $request->year,
            'per_page' => $request->get('per_page', 15),
        ];

        $requests = $this->vacationService->getTeamRequests($user, $filters);

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/my-decisions",
     *     tags={"Vacaciones"},
     *     summary="Historial de decisiones del supervisor",
     *     description="Lista todas las solicitudes que el supervisor ha aprobado o rechazado",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lista de decisiones del supervisor")
     * )
     */
    public function myDecisions(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Obtener tenant IDs del middleware
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'status' => $request->status,
            'year' => $request->year,
            'per_page' => $request->get('per_page', 15),
        ];

        $requests = $this->vacationService->getMyDecisions($user, $filters);

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }
}
