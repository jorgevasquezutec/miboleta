<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateVacationRequestRequest;
use App\Http\Requests\RejectVacationRequestRequest;
use App\Http\Resources\VacationRequestResource;
use App\Models\Scopes\TenantFilterScope;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\ActiveTenantResolver;
use App\Services\VacationBalanceService;
use App\Services\VacationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
        protected VacationService $vacationService,
        protected VacationBalanceService $vacationBalanceService,
        protected ActiveTenantResolver $activeTenantResolver
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/vacation-requests/balance",
     *     tags={"Vacaciones"},
     *     summary="Saldo de vacaciones del usuario autenticado",
     *     description="Calcula el saldo disponible del usuario para una empresa (o la primaria si no se indica) y devuelve el aprobador asignado",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="tenant_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Saldo de vacaciones"),
     *     @OA\Response(response=400, description="Tenant no especificado"),
     *     @OA\Response(response=403, description="No pertenece a la empresa indicada")
     * )
     */
    public function balance(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $tenantId = $request->query('tenant_id')
            ? (int) $request->query('tenant_id')
            : ($user->primaryTenant()?->id ?? $user->tenants->first()?->id);

        if (!$tenantId) {
            return response()->json(['message' => 'Tenant no especificado'], 400);
        }

        // Root puede consultar cualquier empresa; el resto solo las suyas.
        // isRoot() en vez de getCurrentRole() === 'root': determinístico.
        if (!$user->isRoot() && !$user->belongsToTenant($tenantId)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $balance = $this->vacationBalanceService->getBalance($user, $tenantId);
        $approver = $this->vacationBalanceService->getSupervisorForTenant($user, $tenantId);

        // Conteo de solicitudes propias por estado, para las tarjetas
        // "Solicitudes Pendiente" / "Aprobada" de "Mis Vacaciones". Se
        // calcula aquí (no en el listado paginado) porque el listado solo
        // trae la página actual: contar sobre esos 10-15 registros
        // subestimaba el total real (bug E2).
        //
        // withoutGlobalScope igual que VacationBalanceService::takenDaysQuery:
        // el saldo no debe depender del filtro de tenant activo del switcher.
        $counts = VacationRequest::withoutGlobalScope(TenantFilterScope::class)
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [VacationRequest::STATUS_PENDING, VacationRequest::STATUS_APPROVED])
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return response()->json([
            'data' => array_merge($balance, [
                'approver' => $approver ? [
                    'id' => $approver->id,
                    'full_name' => $approver->full_name,
                    'email' => $approver->email,
                ] : null,
                'requests' => [
                    'pending' => (int) ($counts[VacationRequest::STATUS_PENDING] ?? 0),
                    'approved' => (int) ($counts[VacationRequest::STATUS_APPROVED] ?? 0),
                ],
            ]),
        ]);
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

        // El rol se resuelve dentro de la empresa ACTIVA, no con el respaldo
        // global: antes, quien fuera admin en cualquiera de sus empresas
        // resolvía 'admin' y con scope=tenant listaba TODAS las solicitudes de
        // la empresa activa, aunque allí solo fuera client (fuga entre empresas).
        $role = User::roleForTenant($user, $this->activeTenantResolver->resolve($request, $user));

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
        $isTenantScope = $scope === 'tenant' && in_array($role, ['root', 'admin']);

        if ($isTenantScope) {
            $requests = $this->vacationService->getAllRequests($user, $filters);
        } else {
            $requests = $this->vacationService->getRequestsForUser($user, $filters);
        }

        $meta = [
            'current_page' => $requests->currentPage(),
            'last_page' => $requests->lastPage(),
            'per_page' => $requests->perPage(),
            'total' => $requests->total(),
        ];

        // Conteos de "Aprobadas" / "Tomadas" para las tarjetas de
        // VacationHistoryPage, calculados sobre TODO el conjunto filtrado (no
        // solo la página actual). Se calculan en las DOS ramas: antes solo en
        // la de empresa, así que a quien no fuera root/admin no le llegaban y
        // las tarjetas mostraban 0 aunque la tabla listara aprobadas.
        $counts = $isTenantScope
            ? $this->vacationService->getAllRequestsCounts($user, $filters)
            : $this->vacationService->getOwnRequestsCounts($user, $filters);

        $meta['approved_count'] = $counts['approved'];
        $meta['taken_count'] = $counts['taken'];

        return response()->json([
            'data' => VacationRequestResource::collection($requests),
            'meta' => $meta,
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
            return response()->json(['message' => 'Tenant no especificado'], 400);
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
            return response()->json(['message' => $e->getMessage()], 400);
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

        // El rol se resuelve dentro de la empresa DE LA SOLICITUD. Antes se
        // usaba el respaldo global: quien fuera admin en la empresa A podía leer
        // solicitudes ajenas de la B donde solo es client (el rol global no
        // resolvía 'client', así que el check no lo frenaba).
        //
        // Además se invierte a lista blanca: el check anterior ("no es client")
        // dejaba pasar también a quien no tuviera NINGÚN rol en esa empresa
        // (rol null), o sea era fail-open. Ahora, sin rol en la empresa de la
        // solicitud, solo se ve la propia.
        $isOwner = $vacationRequest->user_id === $user->id;
        $role = User::roleForTenant($user, $vacationRequest->tenant_id);

        if (!$isOwner && !in_array($role, ['root', 'admin', 'admin_tenant', 'aprobador'], true)) {
            return response()->json(['message' => 'No autorizado'], 403);
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
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
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
        $this->attachVacationBalances($requests);

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
        $this->attachVacationBalances($requests);

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
        $this->attachVacationBalances($requests);

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
     * Adjunta a cada solicitud de la página el saldo de vacaciones del
     * EMPLEADO SOLICITANTE (los 4 conceptos del cliente: Pendientes, Gozadas,
     * Truncas y Saldo), para que quien aprueba decida con contexto:
     * approveRequest() NO revalida el saldo —solo se valida al crear la
     * solicitud, ver VacationRequestFormPage—, así que entre la solicitud y la
     * aprobación el saldo pudo bajar por otras aprobaciones y hoy nada avisa.
     *
     * Agrupado por tenant_id porque el saldo es POR EMPRESA (hire_date y
     * régimen laboral propios de cada una, ver VacationBalanceService) y una
     * misma página puede mezclar empresas cuando el switcher está en "todas".
     * Coste: 2 queries por empresa presente en la página —no 2 por solicitud—,
     * porque getBalancesForUsers() resuelve el lote completo con un whereIn y
     * un agregado con groupBy.
     *
     * El saldo se cuelga como atributo dinámico del modelo (no es columna de
     * `vacation_requests`) únicamente para que VacationRequestResource lo
     * serialice. Los tres endpoints que llaman a este helper son de solo
     * lectura y nunca hacen save() sobre estas instancias.
     */
    private function attachVacationBalances(LengthAwarePaginator $requests): void
    {
        $requests->getCollection()
            ->groupBy('tenant_id')
            ->each(function ($group, $tenantId) {
                // `user` viene eager-loaded en los tres listados. Si faltara
                // (usuario borrado), esa solicitud simplemente queda sin saldo
                // en vez de romper el listado entero.
                $users = $group->pluck('user')->filter()->unique('id')->values();

                if ($users->isEmpty()) {
                    return;
                }

                $balances = $this->vacationBalanceService->getBalancesForUsers($users, (int) $tenantId);

                $group->each(function ($vacationRequest) use ($balances) {
                    $vacationRequest->vacation_balance = $balances[$vacationRequest->user_id] ?? null;
                });
            });
    }
}
