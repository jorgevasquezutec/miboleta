<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Audit Service
 * 
 * Centralized service for logging all important actions in the system.
 */
class AuditService
{
    /**
     * Log an action to the audit trail.
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?int $userId = null,
        ?int $tenantId = null
    ): AuditLog {
        // Get current user if not provided
        $user = Auth::user();
        $userId = $userId ?? $user?->id;
        $tenantId = $tenantId ?? $user?->primaryTenant()?->id;

        $auditLog = AuditLog::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);

        Log::debug('[AuditService] Action logged', [
            'action' => $action,
            'user_id' => $userId,
            'entity' => $entityType ? "{$entityType}:{$entityId}" : null,
        ]);

        return $auditLog;
    }

    // ============ Authentication Actions ============

    public function logLogin(int $userId, ?int $tenantId = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_USER_LOGIN,
            userId: $userId,
            tenantId: $tenantId
        );
    }

    public function logLogout(): AuditLog
    {
        return $this->log(AuditLog::ACTION_USER_LOGOUT);
    }

    public function logLoginFailed(string $login, string $reason = 'Invalid credentials'): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_USER_LOGIN_FAILED,
            metadata: ['login' => $login, 'reason' => $reason]
        );
    }

    public function logPasswordChanged(int $userId): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PASSWORD_CHANGED,
            entityType: 'User',
            entityId: $userId,
            userId: $userId
        );
    }

    // ============ User Actions ============

    public function logUserCreated(int $userId, array $userData): AuditLog
    {
        // Remove sensitive data
        unset($userData['password']);

        return $this->log(
            action: AuditLog::ACTION_USER_CREATED,
            entityType: 'User',
            entityId: $userId,
            newValues: $userData
        );
    }

    public function logUserUpdated(int $userId, array $oldData, array $newData): AuditLog
    {
        // Remove sensitive data
        unset($oldData['password'], $newData['password']);

        return $this->log(
            action: AuditLog::ACTION_USER_UPDATED,
            entityType: 'User',
            entityId: $userId,
            oldValues: $oldData,
            newValues: $newData
        );
    }

    public function logUserDeleted(int $userId, array $userData): AuditLog
    {
        unset($userData['password']);

        return $this->log(
            action: AuditLog::ACTION_USER_DELETED,
            entityType: 'User',
            entityId: $userId,
            oldValues: $userData
        );
    }

    // ============ Document Actions ============

    public function logDocumentUploaded(int $documentId, string $filename, ?int $tenantId = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_UPLOADED,
            entityType: 'Document',
            entityId: $documentId,
            metadata: ['filename' => $filename],
            tenantId: $tenantId
        );
    }

    public function logDocumentViewed(int $documentId): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_VIEWED,
            entityType: 'Document',
            entityId: $documentId
        );
    }

    public function logDocumentDownloaded(int $documentId): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_DOWNLOADED,
            entityType: 'Document',
            entityId: $documentId
        );
    }

    public function logDocumentSigned(int $documentId, int $userId): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_SIGNED,
            entityType: 'Document',
            entityId: $documentId,
            userId: $userId
        );
    }

    public function logDocumentDeleted(int $documentId, array $documentData): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_DELETED,
            entityType: 'Document',
            entityId: $documentId,
            oldValues: $documentData
        );
    }

    public function logBatchCreated(int $batchId, int $documentCount, ?int $tenantId = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_BATCH_CREATED,
            entityType: 'DocumentBatch',
            entityId: $batchId,
            metadata: ['document_count' => $documentCount],
            tenantId: $tenantId
        );
    }

    public function logBatchCompleted(int $batchId, int $successCount, int $failedCount): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_BATCH_COMPLETED,
            entityType: 'DocumentBatch',
            entityId: $batchId,
            metadata: [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]
        );
    }

    // ============ Vacation Actions ============

    public function logVacationRequested(int $vacationId, array $data): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_REQUESTED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            newValues: $data
        );
    }

    public function logVacationApproved(int $vacationId, ?string $comment = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_APPROVED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $comment ? ['comment' => $comment] : null
        );
    }

    public function logVacationRejected(int $vacationId, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_REJECTED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $reason ? ['reason' => $reason] : null
        );
    }

    public function logVacationConfirmed(int $vacationId, bool $wasTaken): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_CONFIRMED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: ['was_taken' => $wasTaken]
        );
    }

    public function logVacationCancelled(int $vacationId, ?string $reason = null): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_CANCELLED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $reason ? ['reason' => $reason] : null
        );
    }

    // ============ Tenant Actions ============

    public function logTenantCreated(int $tenantId, array $data): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_TENANT_CREATED,
            entityType: 'Tenant',
            entityId: $tenantId,
            newValues: $data,
            tenantId: $tenantId
        );
    }

    public function logTenantUpdated(int $tenantId, array $oldData, array $newData): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_TENANT_UPDATED,
            entityType: 'Tenant',
            entityId: $tenantId,
            oldValues: $oldData,
            newValues: $newData,
            tenantId: $tenantId
        );
    }

    public function logTenantDeleted(int $tenantId, array $data): AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_TENANT_DELETED,
            entityType: 'Tenant',
            entityId: $tenantId,
            oldValues: $data,
            tenantId: $tenantId
        );
    }
}
