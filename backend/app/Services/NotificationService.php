<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a new notification.
     */
    public function create(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?array $data = null,
        ?string $actionUrl = null,
        ?int $tenantId = null
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'action_url' => $actionUrl,
        ]);

        Log::info('[NotificationService] Notification created', [
            'id' => $notification->id,
            'user_id' => $userId,
            'type' => $type,
        ]);

        // Broadcast to user's private channel for real-time updates
        try {
            $event = new NotificationCreated($notification);
            broadcast($event);

            Log::info('[NotificationService] Broadcast dispatched', [
                'notification_id' => $notification->id,
                'channel' => 'private-user.' . $userId,
            ]);
        } catch (\Exception $e) {
            Log::warning('[NotificationService] Failed to broadcast notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $notification;
    }

    /**
     * Get notifications for a user.
     */
    public function getForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Notification::forUser($user->id)
            ->orderBy('created_at', 'desc');

        // Filter by tenant(s) if specified
        if (!empty($filters['tenant_ids'])) {
            $query->forTenant($filters['tenant_ids']);
        } elseif (!empty($filters['tenant_id'])) {
            // Legacy support
            $query->forTenant($filters['tenant_id']);
        }

        // Filter by read status
        if (isset($filters['unread_only']) && $filters['unread_only']) {
            $query->unread();
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get unread count for a user.
     */
    public function getUnreadCount(User $user, int|array|null $tenantIds = null): int
    {
        $query = Notification::forUser($user->id)->unread();

        if ($tenantIds) {
            $query->forTenant($tenantIds);
        }

        return $query->count();
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): Notification
    {
        $notification->markAsRead();
        return $notification->fresh();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user, int|array|null $tenantIds = null): int
    {
        $query = Notification::forUser($user->id)->unread();

        if ($tenantIds) {
            $query->forTenant($tenantIds);
        }

        $count = $query->count();
        $query->update(['read_at' => now()]);

        Log::info('[NotificationService] Marked all as read', [
            'user_id' => $user->id,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Delete a notification.
     */
    public function delete(Notification $notification): void
    {
        $notification->delete();
    }

    /**
     * Delete all read notifications older than X days.
     */
    public function cleanupOld(int $daysOld = 30): int
    {
        $count = Notification::read()
            ->where('created_at', '<', now()->subDays($daysOld))
            ->delete();

        Log::info('[NotificationService] Cleaned up old notifications', [
            'count' => $count,
            'days_old' => $daysOld,
        ]);

        return $count;
    }

    // ============ Notification Creators ============

    /**
     * Notify user about a new document.
     */
    public function notifyNewDocument(
        int $userId,
        int $documentId,
        string $documentName,
        int $tenantId
    ): Notification {
        return $this->create(
            userId: $userId,
            type: Notification::TYPE_DOCUMENT_NEW,
            title: 'Nuevo documento disponible',
            message: "Tienes un nuevo documento: {$documentName}",
            data: ['document_id' => $documentId],
            actionUrl: '/dashboard',
            tenantId: $tenantId
        );
    }

    /**
     * Notify admin about a signed document.
     */
    public function notifyDocumentSigned(
        int $adminUserId,
        int $documentId,
        string $documentName,
        string $signerName,
        int $tenantId
    ): Notification {
        return $this->create(
            userId: $adminUserId,
            type: Notification::TYPE_DOCUMENT_SIGNED,
            title: 'Documento firmado',
            message: "{$signerName} ha firmado: {$documentName}",
            data: ['document_id' => $documentId],
            actionUrl: '/documents',
            tenantId: $tenantId
        );
    }

    /**
     * Notify supervisor about a new vacation request.
     */
    public function notifyVacationCreated(
        int $supervisorId,
        int $vacationId,
        string $employeeName,
        string $dateRange,
        int $tenantId
    ): Notification {
        return $this->create(
            userId: $supervisorId,
            type: Notification::TYPE_VACATION_CREATED,
            title: 'Nueva solicitud de vacaciones',
            message: "{$employeeName} solicita vacaciones: {$dateRange}",
            data: ['vacation_id' => $vacationId],
            actionUrl: '/team-vacations',
            tenantId: $tenantId
        );
    }

    /**
     * Notify employee about approved vacation.
     */
    public function notifyVacationApproved(
        int $employeeId,
        int $vacationId,
        string $dateRange,
        string $approverName,
        int $tenantId
    ): Notification {
        return $this->create(
            userId: $employeeId,
            type: Notification::TYPE_VACATION_APPROVED,
            title: '¡Vacaciones aprobadas!',
            message: "Tus vacaciones ({$dateRange}) fueron aprobadas por {$approverName}",
            data: ['vacation_id' => $vacationId],
            actionUrl: '/vacations',
            tenantId: $tenantId
        );
    }

    /**
     * Notify employee about rejected vacation.
     */
    public function notifyVacationRejected(
        int $employeeId,
        int $vacationId,
        string $dateRange,
        string $rejecterName,
        ?string $reason,
        int $tenantId
    ): Notification {
        $message = "Tus vacaciones ({$dateRange}) fueron rechazadas por {$rejecterName}";
        if ($reason) {
            $message .= ". Motivo: {$reason}";
        }

        return $this->create(
            userId: $employeeId,
            type: Notification::TYPE_VACATION_REJECTED,
            title: 'Vacaciones rechazadas',
            message: $message,
            data: ['vacation_id' => $vacationId, 'reason' => $reason],
            actionUrl: '/vacations',
            tenantId: $tenantId
        );
    }

    /**
     * Notify supervisor about pending vacation confirmation.
     */
    public function notifyVacationPendingConfirmation(
        int $supervisorId,
        int $vacationId,
        string $employeeName,
        string $dateRange,
        int $tenantId
    ): Notification {
        return $this->create(
            userId: $supervisorId,
            type: Notification::TYPE_VACATION_PENDING_CONFIRMATION,
            title: 'Vacaciones por confirmar',
            message: "Las vacaciones de {$employeeName} ({$dateRange}) terminaron. Confirma si fueron tomadas.",
            data: ['vacation_id' => $vacationId],
            actionUrl: '/team-vacations',
            tenantId: $tenantId
        );
    }
}
