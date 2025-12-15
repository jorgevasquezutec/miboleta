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
use Illuminate\Support\Facades\Mail;

class VacationService
{
    public function __construct(
        protected NotificationService $notificationService
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
        // Verify user has a supervisor
        if (!$user->immediate_supervisor_id) {
            throw new \InvalidArgumentException('No tienes un supervisor asignado. Contacta a RRHH.');
        }

        // Validate no overlap with existing approved vacations
        $this->validateNoOverlap($user->id, $data['start_date'], $data['end_date']);

        $vacationRequest = VacationRequest::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'days_requested' => $data['days_requested'],
            'reason' => $data['reason'] ?? null,
            'status' => VacationRequest::STATUS_PENDING,
        ]);

        // Load relationships
        $vacationRequest->load(['user', 'user.immediateSupervisor']);

        // Notify supervisor
        $this->notifySupervisor($vacationRequest);

        Log::info('[VacationService] Vacation request created', [
            'request_id' => $vacationRequest->id,
            'user_id' => $user->id,
            'supervisor_id' => $user->immediate_supervisor_id,
            'days' => $data['days_requested'],
        ]);

        return $vacationRequest;
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
        $query = VacationRequest::with(['approvedByUser', 'rejectedByUser', 'confirmedByUser'])
            ->forUser($user->id)
            ->orderBy('created_at', 'desc');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['year'])) {
            $query->whereYear('start_date', $filters['year']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
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
        $role = $user->getCurrentRole();
        $tenantId = $filters['tenant_id'] ?? $user->tenants->first()?->id;

        $query = VacationRequest::with(['user', 'approvedByUser', 'rejectedByUser', 'confirmedByUser'])
            ->orderBy('created_at', 'desc');

        // Apply tenant filter for non-root users
        if ($role !== 'root' && $tenantId) {
            $query->forTenant($tenantId);
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

        return $query->paginate($filters['per_page'] ?? 15);
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
     * Verify that the user is the supervisor of the request owner.
     *
     * @param VacationRequest $request
     * @param User $supervisor
     * @throws UnauthorizedAccessException
     */
    protected function verifySupervisor(VacationRequest $request, User $supervisor): void
    {
        $request->load('user');

        $role = $supervisor->getCurrentRole();

        // Root and admin can also approve
        if (in_array($role, ['root', 'admin'])) {
            return;
        }

        if ($request->user->immediate_supervisor_id !== $supervisor->id) {
            throw new UnauthorizedAccessException('No eres el supervisor de este empleado.');
        }
    }

    /**
     * Notify supervisor about new vacation request.
     */
    protected function notifySupervisor(VacationRequest $request): void
    {
        $supervisor = $request->user->immediateSupervisor;
        if (!$supervisor) {
            return;
        }

        $dateRange = $request->start_date->format('d/m') . ' - ' . $request->end_date->format('d/m/Y');
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

        // Email notification
        try {
            Mail::to($supervisor->email)->send(new VacationRequestCreatedMail($request));
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to send supervisor email', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
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

        // Email notification
        try {
            Mail::to($request->user->email)->send(new VacationRequestApprovedMail($request));
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

        // Email notification
        try {
            Mail::to($request->user->email)->send(new VacationRequestRejectedMail($request));
        } catch (\Exception $e) {
            Log::warning('[VacationService] Failed to send rejection email', [
                'request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
