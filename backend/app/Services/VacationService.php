<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Mail\VacationRequestApprovedMail;
use App\Mail\VacationRequestCreatedMail;
use App\Mail\VacationRequestRejectedMail;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class VacationService
{
    public function __construct(
        protected NotificationService $notificationService,
        protected AuditService $auditService,
        protected VacationBalanceService $vacationBalanceService,
        protected TenantMailerService $tenantMailerService
    ) {
    }
    /**
     * Create a new vacation request.
     *
     * @param array $data
     * @param User $user
     * @param int $tenantId
     * @return VacationRequest
     * @throws \InvalidArgumentException
     */
    public function createRequest(array $data, User $user, int $tenantId): VacationRequest
    {
        // Verify user has a supervisor for this tenant
        $supervisor = $user->getSupervisorForTenant($tenantId);
        if (!$supervisor) {
            // Dos causas distintas detrás del mismo null: nunca hubo
            // supervisor, o el asignado fue eliminado (getSupervisorForTenant
            // aplica el scope de SoftDeletes). Antes ambas daban "No tienes un
            // supervisor asignado", lo que mandaba a RRHH a buscar una
            // asignación que sí existe en el pivote, en vez de a habilitar o
            // reemplazar la cuenta eliminada.
            throw new \InvalidArgumentException(
                $user->supervisorIdForTenant($tenantId)
                    ? 'Tu supervisor asignado fue eliminado. Contacta a RRHH para que te asignen uno nuevo o habiliten su cuenta.'
                    : 'No tienes un supervisor asignado para esta empresa. Contacta a RRHH.'
            );
        }

        // Validate no overlap with existing approved vacations
        $this->validateNoOverlap($user->id, $data['start_date'], $data['end_date']);

        // Validate the requested days do not exceed the available balance
        $this->validateSufficientBalance($user, $tenantId, (float) $data['days_requested']);

        $vacationRequest = VacationRequest::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_requested' => $data['days_requested'],
            'reason' => $data['reason'] ?? null,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        // Asignar el supervisor que era válido al momento de crear la solicitud
        // En una implementación futura, podríamos guardar el supervisor_id en la solicitud para historial
        // $vacationRequest->supervisor_id = $supervisor->id; 
        // $vacationRequest->save();

        // Load relationships (supervisor will be loaded manually when needed)
        $vacationRequest->load(['user']);

        // Notify supervisor
        $this->notifySupervisor($vacationRequest, $supervisor);

        // Audit log
        $this->auditService->logVacationRequested($vacationRequest->id, [
            'user_id' => $user->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_requested' => $data['days_requested'],
            'supervisor_id' => $supervisor->id,
            'tenant_id' => $tenantId,
        ]);

        Log::info('[VacationService] Vacation request created', [
            'request_id' => $vacationRequest->id,
            'user_id' => $user->id,
            'supervisor_id' => $supervisor->id,
            'days' => $data['days_requested'],
        ]);

        return $vacationRequest;
    }

    /**
     * Notify supervisor about new vacation request.
     */
    protected function notifySupervisor(VacationRequest $request, ?User $explicitSupervisor = null): void
    {
        // Si nos pasan el supervisor explícitamente, lo usamos (para evitar double-fetching)
        // si no, lo buscamos basado en el tenant
        $supervisor = $explicitSupervisor ?? $request->user->getSupervisorForTenant($request->tenant_id);

        if (!$supervisor) {
            Log::warning('[VacationService] No supervisor found for notification', [
                'request_id' => $request->id,
                'user_id' => $request->user_id,
                'tenant_id' => $request->tenant_id
            ]);
            return;
        }

        $dateRange = "";
        try {
            // Ensure dates are Carbon objects before formatting
            $start = $request->start_date instanceof \Carbon\Carbon ? $request->start_date : \Carbon\Carbon::parse($request->start_date);
            $end = $request->end_date instanceof \Carbon\Carbon ? $request->end_date : \Carbon\Carbon::parse($request->end_date);
            $dateRange = $start->format('d/m') . ' - ' . $end->format('d/m/Y');
        } catch (\Exception $e) {
            $dateRange = "Fechas inválidas";
            Log::error("Error formatting dates for email: " . $e->getMessage());
        }

        $employeeName = $request->user->name . ' ' . $request->user->last_name;

        // In-app notification
        try {
            $this->notificationService->notifyVacationCreated(
                supervisorId: $supervisor->id,
                vacationId: $request->id,
                employeeName: $employeeName,
                dateRange: $dateRange,
                tenantId: $request->tenant_id
            );
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to create in-app notification', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Email notification (enrutado por el mailer propio de la empresa
        // de la solicitud, con fallback al de la plataforma; ver
        // TenantMailerService)
        try {
            $this->tenantMailerService->send($request->tenant, $supervisor->email, new VacationRequestCreatedMail($request));
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to send supervisor email', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Approve a vacation request.
     *
     * @param VacationRequest $request
     * @param User $approver
     * @return VacationRequest
     * @throws UnauthorizedAccessException
     */
    public function approveRequest(VacationRequest $request, User $approver): VacationRequest
    {
        // Verify approver is the supervisor
        $this->verifySupervisor($request, $approver);

        if (!$request->isPending()) {
            throw new \InvalidArgumentException('Esta solicitud ya no está pendiente.');
        }

        $request->approve($approver);

        // Notify employee
        $this->notifyEmployeeApproved($request);

        // Audit log
        $this->auditService->logVacationApproved($request->id);

        Log::info('[VacationService] Vacation request approved', [
            'request_id' => $request->id,
            'approved_by' => $approver->id,
        ]);

        return $request->fresh(['user', 'approvedByUser']);
    }

    /**
     * Reject a vacation request.
     *
     * @param VacationRequest $request
     * @param User $rejector
     * @param string|null $reason
     * @return VacationRequest
     * @throws UnauthorizedAccessException
     */
    public function rejectRequest(VacationRequest $request, User $rejector, ?string $reason = null): VacationRequest
    {
        // Verify rejector is the supervisor
        $this->verifySupervisor($request, $rejector);

        if (!$request->isPending()) {
            throw new \InvalidArgumentException('Esta solicitud ya no está pendiente.');
        }

        $request->reject($rejector, $reason);

        // Notify employee
        $this->notifyEmployeeRejected($request);

        // Audit log
        $this->auditService->logVacationRejected($request->id, $reason);

        Log::info('[VacationService] Vacation request rejected', [
            'request_id' => $request->id,
            'rejected_by' => $rejector->id,
            'reason' => $reason,
        ]);

        return $request->fresh(['user', 'rejectedByUser']);
    }

    /**
     * Cancel a vacation request (by employee).
     *
     * @param VacationRequest $request
     * @param User $user
     * @return VacationRequest
     * @throws UnauthorizedAccessException
     */
    public function cancelRequest(VacationRequest $request, User $user): VacationRequest
    {
        if ($request->user_id !== $user->id) {
            throw new UnauthorizedAccessException('No puedes cancelar esta solicitud.');
        }

        if (!$request->isPending()) {
            throw new \InvalidArgumentException('Solo puedes cancelar solicitudes pendientes.');
        }

        $request->cancel();

        // Audit log
        $this->auditService->logVacationCancelled($request->id);

        Log::info('[VacationService] Vacation request cancelled', [
            'request_id' => $request->id,
            'user_id' => $user->id,
        ]);

        return $request->fresh();
    }

    /**
     * Mark vacation as taken (by supervisor).
     *
     * @param VacationRequest $request
     * @param User $confirmer
     * @return VacationRequest
     * @throws UnauthorizedAccessException
     */
    public function markAsTaken(VacationRequest $request, User $confirmer): VacationRequest
    {
        $this->verifySupervisor($request, $confirmer);

        if (!$request->isApproved()) {
            throw new \InvalidArgumentException('Solo puedes confirmar vacaciones aprobadas.');
        }

        $request->markAsTaken($confirmer);

        $this->auditService->logVacationConfirmed($request->id, true);

        Log::info('[VacationService] Vacation marked as taken', [
            'request_id' => $request->id,
            'confirmed_by' => $confirmer->id,
        ]);

        return $request->fresh(['user', 'confirmedByUser']);
    }

    /**
     * Mark vacation as NOT taken (by supervisor).
     *
     * @param VacationRequest $request
     * @param User $confirmer
     * @return VacationRequest
     * @throws UnauthorizedAccessException
     */
    public function markAsNotTaken(VacationRequest $request, User $confirmer): VacationRequest
    {
        $this->verifySupervisor($request, $confirmer);

        if (!$request->isApproved()) {
            throw new \InvalidArgumentException('Solo puedes confirmar vacaciones aprobadas.');
        }

        $request->markAsNotTaken($confirmer);

        $this->auditService->logVacationConfirmed($request->id, false);

        Log::info('[VacationService] Vacation marked as NOT taken', [
            'request_id' => $request->id,
            'confirmed_by' => $confirmer->id,
        ]);

        return $request->fresh(['user', 'confirmedByUser']);
    }

    /**
     * Get vacation requests for a user.
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getRequestsForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        // 'user' en el eager load: sin él, VacationRequestResource omite la
        // clave entera (va con when(relationLoaded('user'))) y el front pintaba
        // "Usuario desconocido" en el Histórico del empleado, mientras el admin
        // —que cae en getAllRequests, donde sí estaba— veía el nombre bien.
        // Arrastraba también el bloque 'approver', gateado por la misma
        // relación.
        return $this->buildOwnRequestsQuery($user, $filters)
            ->with(['user', 'approvedByUser', 'rejectedByUser', 'confirmedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Conteos de "Aprobadas" / "Tomadas" sobre las solicitudes PROPIAS, para
     * las tarjetas de resumen del Histórico cuando lo abre alguien que no es
     * root ni admin. Espeja getAllRequestsCounts(): antes solo existía la
     * versión de empresa, así que al empleado no le llegaban los conteos y las
     * tarjetas mostraban 0 aunque la tabla listara solicitudes aprobadas.
     *
     * @param User $user
     * @param array $filters
     * @return array{total:int, approved:int, taken:int}
     */
    public function getOwnRequestsCounts(User $user, array $filters = []): array
    {
        $base = $this->buildOwnRequestsQuery($user, $filters);

        return [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)->where('status', VacationRequest::STATUS_APPROVED)->count(),
            'taken' => (clone $base)->where('was_taken', true)->count(),
        ];
    }

    /**
     * Query base (sin eager loads, sin orden, sin paginar) de las solicitudes
     * PROPIAS, compartida por getRequestsForUser() y getOwnRequestsCounts()
     * para que listado y conteos apliquen exactamente los mismos filtros —
     * mismo patrón que buildAllRequestsQuery() para la vista de empresa.
     *
     * Aplica date_from/date_to/search además de tenant/status/year: el
     * Histórico muestra esos tres controles a cualquier rol, pero solo la rama
     * de empresa los filtraba, así que para el empleado el rango de fechas y
     * el buscador se ignoraban en silencio.
     *
     * @param User $user
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildOwnRequestsQuery(User $user, array $filters = [])
    {
        $query = VacationRequest::query()->forUser($user->id);

        // Filter by tenant if provided
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        // Por created_at, igual que buildAllRequestsQuery: el rango del
        // Histórico es "cuándo se solicitó", no "cuándo se vacaciona".
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->matchingFullName($search)
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Get pending approvals for supervisor.
     *
     * @param User $supervisor
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPendingApprovals(User $supervisor, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $supervisor->tenants->first()?->id;

        $query = VacationRequest::with(['user'])
            ->forSupervisor($supervisor->id)
            ->pending()
            ->orderBy('created_at', 'asc');

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get approved vacations pending confirmation.
     *
     * @param User $supervisor
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPendingConfirmations(User $supervisor, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $supervisor->tenants->first()?->id;

        $query = VacationRequest::with(['user', 'approvedByUser'])
            ->forSupervisor($supervisor->id)
            ->pendingConfirmation()
            ->where('end_date', '<', now()) // Solo las que ya pasaron
            ->orderBy('end_date', 'asc');

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get vacation requests for supervisor's team.
     *
     * @param User $supervisor
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getTeamRequests(User $supervisor, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $supervisor->tenants->first()?->id;

        $query = VacationRequest::with(['user', 'approvedByUser', 'rejectedByUser'])
            ->forSupervisor($supervisor->id)
            ->orderBy('created_at', 'desc');

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get vacation requests that this supervisor has approved or rejected (decision history).
     *
     * @param User $supervisor
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getMyDecisions(User $supervisor, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $supervisor->tenants->first()?->id;

        $query = VacationRequest::with(['user', 'approvedByUser', 'rejectedByUser', 'confirmedByUser'])
            ->where(function ($q) use ($supervisor) {
                $q->where('approved_by', $supervisor->id)
                    ->orWhere('rejected_by', $supervisor->id);
            })
            ->whereIn('status', [VacationRequest::STATUS_APPROVED, VacationRequest::STATUS_REJECTED])
            ->orderBy('updated_at', 'desc');

        if ($tenantId) {
            $query->forTenant($tenantId);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get all vacation requests (admin view).
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getAllRequests(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->buildAllRequestsQuery($user, $filters)
            ->with(['user', 'approvedByUser', 'rejectedByUser', 'confirmedByUser'])
            ->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Conteos de "Histórico de Vacaciones" (VacationHistoryPage) sobre el
     * MISMO conjunto filtrado que ve la tabla — no sobre la página actual.
     *
     * Bug corregido: el frontend calculaba "Aprobadas" y "Tomadas" con
     * Array.filter sobre `historyRequests` (solo la página actual, 10 por
     * defecto), así que nunca superaban el tamaño de página y cambiaban al
     * paginar. Es el mismo bug ya resuelto en el endpoint de balance para
     * "Mis Vacaciones" (ver VacationRequestController::balance).
     *
     * Criterio de diseño (a propósito): los conteos se calculan sobre la
     * MISMA query filtrada que arma la tabla, incluyendo el filtro de
     * `status` si el usuario lo aplicó. Así, si el usuario filtra por
     * "Aprobadas", la tarjeta "Total" y la tarjeta "Aprobadas" muestran el
     * mismo número — tabla y tarjetas siempre cuadran, sin un significado
     * oculto de "aprobadas dentro de aprobadas".
     *
     * @param User $user
     * @param array $filters
     * @return array{total: int, approved: int, taken: int}
     */
    public function getAllRequestsCounts(User $user, array $filters = []): array
    {
        $base = $this->buildAllRequestsQuery($user, $filters);

        return [
            'total' => (clone $base)->count(),
            'approved' => (clone $base)->where('status', VacationRequest::STATUS_APPROVED)->count(),
            'taken' => (clone $base)->where('was_taken', true)->count(),
        ];
    }

    /**
     * Query base (sin eager loads, sin orden, sin paginar) compartida por
     * getAllRequests() y getAllRequestsCounts(), para que el listado y sus
     * conteos apliquen EXACTAMENTE los mismos filtros.
     *
     * @param User $user
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildAllRequestsQuery(User $user, array $filters = [])
    {
        // isRoot() en vez de getCurrentRole() === 'root': determinístico y sin
        // depender del respaldo global de roles (root es global por diseño).
        $isRoot = $user->isRoot();
        $tenantId = $filters['tenant_id'] ?? $user->tenants->first()?->id;

        $query = VacationRequest::query();

        // Apply tenant filter:
        // - Non-root users always filter by tenant
        // - Root users only filter if they explicitly specify a tenant_id
        if (!$isRoot && $tenantId) {
            $query->forTenant($tenantId);
        } elseif ($isRoot && !empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        if (!empty($filters['was_taken']) && $filters['was_taken'] !== 'all') {
            if ($filters['was_taken'] === 'pending') {
                $query->whereNull('was_taken');
            } else {
                $query->where('was_taken', $filters['was_taken'] === 'true');
            }
        }

        // Date range filters (by created_at)
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // Search filter (by user full name or email). Nombre completo vía
        // User::scopeMatchingFullName: antes comparaba 'name' y 'last_name'
        // por separado, así que "Juan Pérez" no encontraba a un empleado con
        // name=Juan, last_name=Pérez (ninguna columna contiene la cadena
        // completa). Ver docblock del scope para la nota de portabilidad
        // sqlite/MySQL.
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->matchingFullName($search)
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Validate no overlapping vacations.
     *
     * @param int $userId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $excludeId
     * @throws \InvalidArgumentException
     */
    public function validateNoOverlap(int $userId, string $startDate, string $endDate, ?int $excludeId = null): void
    {
        $query = VacationRequest::forUser($userId)
            ->whereIn('status', [VacationRequest::STATUS_PENDING, VacationRequest::STATUS_APPROVED])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw new \InvalidArgumentException('Ya tienes vacaciones solicitadas o aprobadas en esas fechas.');
        }
    }

    /**
     * Validate that the requested days do not exceed the user's "Saldo
     * Vacaciones" (Pendientes + Truncas − Gozadas) for this tenant — see
     * VacationBalanceService::computeFourFigures().
     *
     * Se valida contra `balance` (el Saldo), no contra `available` (solo los
     * días de años ya vencidos): así el empleado puede pedir hasta el mismo
     * número que "Mis Vacaciones" le muestra como Saldo. Si valiéramos solo
     * contra los años vencidos, la pantalla mostraría un Saldo mayor al que
     * el sistema deja pedir, y el cliente lo reportaría como bug.
     *
     * Esto permite ADELANTAR vacaciones truncas del período en curso (no
     * vencido). Decisión provisional tomada el 31/07/2026 (SPEC-VACACIONES
     * v2, "pregunta abierta") — PENDIENTE DE CONFIRMAR con el cliente si
     * quiere permitir ese adelanto.
     *
     * @param User $user
     * @param int $tenantId
     * @param float $daysRequested
     * @throws \InvalidArgumentException
     */
    public function validateSufficientBalance(User $user, int $tenantId, float $daysRequested): void
    {
        $balance = $this->vacationBalanceService->getBalance($user, $tenantId);

        if ($daysRequested > $balance['balance']) {
            throw new \InvalidArgumentException(sprintf(
                'No tienes saldo de vacaciones suficiente. Disponible: %s día(s), solicitado: %s día(s).',
                $balance['balance'],
                $daysRequested
            ));
        }
    }

    /**
     * Verify that the user is the supervisor of the request owner.
     *
     * @param VacationRequest $request
     * @param User $supervisor
     * @throws UnauthorizedAccessException
     */
    protected function verifySupervisor(VacationRequest $request, User $supervisor): void
    {
        $request->load('user');

        // El rol se resuelve dentro de la empresa DE LA SOLICITUD, no con el
        // respaldo global. Antes, quien fuera 'admin' en cualquiera de sus
        // empresas resolvía 'admin' vía getCurrentRole() y podía aprobar
        // solicitudes de OTRA empresa donde no es admin (fuga entre empresas).
        //
        // Se preserva a propósito la misma política de hoy (root/admin saltan el
        // chequeo de supervisor; el resto debe ser el supervisor asignado): este
        // paso solo cierra la fuga. El realineamiento a la Matriz de Accesos
        // ('vacations.approve_reject_team' excluye a root) va aparte.
        $role = User::roleForTenant($supervisor, $request->tenant_id);

        // Root and admin can also approve
        if (in_array($role, ['root', 'admin'], true)) {
            return;
        }

        // Solicitante eliminado: load('user') deja la relación cargada pero en
        // null (scope global de SoftDeletes), y el acceso de abajo reventaba
        // con 500. La bandeja del supervisor ya filtra estas solicitudes
        // (scopeForSupervisor usa whereHas('user')), así que aquí solo se
        // llega con un ID viejo —una pestaña abierta antes del borrado—; se
        // responde con el error de autorización normal, no con un 500.
        if (!$request->user) {
            throw new UnauthorizedAccessException('El empleado de esta solicitud ya no está activo.');
        }

        // Check if user is the assigned supervisor for this tenant
        $correctSupervisor = $request->user->getSupervisorForTenant($request->tenant_id);

        if (!$correctSupervisor || $correctSupervisor->id !== $supervisor->id) {
            throw new UnauthorizedAccessException('No eres el supervisor asignado para este empleado en esta empresa.');
        }
    }



    /**
     * Notify employee about approved request.
     */
    protected function notifyEmployeeApproved(VacationRequest $request): void
    {
        $dateRange = $request->start_date->format('d/m') . ' - ' . $request->end_date->format('d/m/Y');
        $approverName = $request->approvedByUser->name . ' ' . $request->approvedByUser->last_name;

        // In-app notification
        try {
            $this->notificationService->notifyVacationApproved(
                employeeId: $request->user_id,
                vacationId: $request->id,
                dateRange: $dateRange,
                approverName: $approverName,
                tenantId: $request->tenant_id
            );
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to create in-app notification', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Email notification with CC to root users (enrutado por el mailer
        // propio de la empresa de la solicitud, con fallback al de la
        // plataforma; ver TenantMailerService)
        try {
            // Get all root users emails for CC
            $rootEmails = User::whereHas('roles', function ($query) {
                $query->where('name', 'root');
            })->where('status', 'active')
                ->pluck('email')
                ->toArray();

            $this->tenantMailerService->send(
                $request->tenant,
                $request->user->email,
                new VacationRequestApprovedMail($request),
                $rootEmails
            );
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to send approval email', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify employee about rejected request.
     */
    protected function notifyEmployeeRejected(VacationRequest $request): void
    {
        $dateRange = $request->start_date->format('d/m') . ' - ' . $request->end_date->format('d/m/Y');
        $rejecterName = $request->rejectedByUser->name . ' ' . $request->rejectedByUser->last_name;

        // In-app notification
        try {
            $this->notificationService->notifyVacationRejected(
                employeeId: $request->user_id,
                vacationId: $request->id,
                dateRange: $dateRange,
                rejecterName: $rejecterName,
                reason: $request->rejection_reason,
                tenantId: $request->tenant_id
            );
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to create in-app notification', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Email notification (enrutado por el mailer propio de la empresa
        // de la solicitud, con fallback al de la plataforma; ver
        // TenantMailerService)
        try {
            $this->tenantMailerService->send($request->tenant, $request->user->email, new VacationRequestRejectedMail($request));
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to send rejection email', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
