<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\AuditSettings;
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
     * Clave de cache del conjunto de acciones con la captura desactivada.
     * Se invalida al guardar desde el mantenedor (AuditSettingsService).
     */
    public const DISABLED_ACTIONS_CACHE_KEY = 'audit:disabled_actions';

    /**
     * Log an action to the audit trail.
     *
     * Devuelve null si la acción está desactivada en el mantenedor de
     * auditoría (ver isActionEnabled()); las acciones AuditLog::ALWAYS_ON
     * siempre se registran.
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
    ): ?AuditLog {
        if (!$this->isActionEnabled($action)) {
            return null;
        }

        // Get current user if not provided
        $user = Auth::user();
        $userId = $userId ?? $user?->id;
        $tenantId = $tenantId ?? $this->resolveActorTenantId($user);

        // Impersonation ("iniciar sesión como"): root opera con el user_id del
        // EMPLEADO (no se altera autorización ni lógica de negocio, ver
        // contrato de AuthService::impersonate), así que el rastro de "quién
        // estaba detrás" no puede ir en $userId — ese campo ya lo usan varios
        // helpers para fijar un actor explícito (p. ej. logUserDeleted) y
        // pisarlo rompería esa convención. impersonator_id se resuelve APARTE
        // y SIEMPRE del canal token (currentAccessToken()->name, marcado por
        // AuthService::impersonate con el prefijo "impersonation:"), nunca del
        // $userId que reciba esta llamada.
        //
        // Punto ciego CONOCIDO y ACEPTADO: los jobs en cola no llevan esta
        // marca (corren sin Auth ni token asociado a la request). No se
        // arregla aquí — no hay token que inspeccionar fuera de un ciclo HTTP.
        // "?->name ?? null" y no solo "?->name": cuando la request se
        // autenticó por el guard de sesión (Sanctum SIN token real —
        // Auth::viaRequest/actingAs()), currentAccessToken() no es null sino
        // un Laravel\Sanctum\TransientToken, que NO tiene propiedad `name`.
        // El "?->" solo protege contra el objeto null; leer una propiedad
        // inexistente en un objeto SÍ existente sigue siendo un error (500).
        $impersonatorId = $user instanceof \App\Models\User
            ? AuthService::impersonatorIdFromTokenName($user->currentAccessToken()?->name ?? null)
            : null;

        $auditLog = AuditLog::create([
            'user_id' => $userId,
            'impersonator_id' => $impersonatorId,
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

    /**
     * Empresa bajo la que se registra una acción cuando el llamador no la
     * indica explícitamente.
     *
     * Para un no-root es la empresa ACTIVA del switcher, no su empresa
     * primaria. Con la primaria, todo lo que un usuario multi-empresa hiciera
     * en la empresa B quedaba registrado bajo la A: además de dejar incompleta
     * la auditoría de B, la de A mostraba actividad ajena a quien solo
     * administra A (fuga entre empresas). ActiveTenantResolver ya cae a la
     * primaria si no hay header —jobs y comandos incluidos—, así que el
     * comportamiento sin request no cambia.
     *
     * Root es global de plataforma y no tiene empresa: sus acciones quedan con
     * tenant_id null salvo que el llamador pase el tenant DEL RECURSO, que es
     * lo correcto para acciones sobre una empresa concreta.
     */
    private function resolveActorTenantId(?object $user): ?int
    {
        if (!$user instanceof \App\Models\User || $user->isRoot()) {
            return null;
        }

        return app(ActiveTenantResolver::class)->resolve(request(), $user);
    }

    /**
     * Determina si una acción debe capturarse según el mantenedor de
     * auditoría. Las acciones ALWAYS_ON siempre se capturan (defensa en
     * profundidad, aunque estuvieran en el set de desactivadas).
     */
    public function isActionEnabled(string $action): bool
    {
        if (AuditLog::isAlwaysOn($action)) {
            return true;
        }

        return !in_array($action, $this->disabledActions(), true);
    }

    /**
     * Conjunto (cacheado) de acciones con la captura desactivada. Fail-safe:
     * ante cualquier fallo de cache/BD devuelve [] → se captura TODO (nunca se
     * omite auditoría por un error).
     *
     * @return array<int, string>
     */
    protected function disabledActions(): array
    {
        try {
            return cache()->remember(
                self::DISABLED_ACTIONS_CACHE_KEY,
                3600,
                fn () => AuditSettings::current()->disabled_actions ?? []
            );
        } catch (\Throwable $e) {
            Log::warning('[AuditService] No se pudo leer la config de auditoría, capturando todo', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Registra un cambio en el mantenedor de auditoría (meta-evento
     * always-on). $old/$new son los conjuntos de acciones desactivadas.
     */
    public function logAuditSettingsUpdated(array $old, array $new): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_AUDIT_SETTINGS_UPDATED,
            entityType: 'AuditSettings',
            oldValues: ['disabled_actions' => array_values($old)],
            newValues: ['disabled_actions' => array_values($new)]
        );
    }

    // ============ Authentication Actions ============

    public function logLogin(int $userId, ?int $tenantId = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_USER_LOGIN,
            userId: $userId,
            tenantId: $tenantId
        );
    }

    public function logLogout(): ?AuditLog
    {
        return $this->log(AuditLog::ACTION_USER_LOGOUT);
    }

    public function logLoginFailed(string $login, string $reason = 'Invalid credentials'): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_USER_LOGIN_FAILED,
            metadata: ['login' => $login, 'reason' => $reason]
        );
    }

    public function logPasswordChanged(int $userId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PASSWORD_CHANGED,
            entityType: 'User',
            entityId: $userId,
            userId: $userId
        );
    }

    public function logPasswordReset(int $userId, array $metadata = []): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PASSWORD_RESET,
            entityType: 'User',
            entityId: $userId,
            metadata: $metadata ?: null,
            userId: $userId
        );
    }

    public function logPasswordResetRequested(string $email): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PASSWORD_RESET_REQUESTED,
            metadata: ['email' => $email]
        );
    }

    // ============ Role Actions (roles por empresa) ============

    public function logRoleAssigned(int $userId, ?int $tenantId, array $roleIds, ?array $oldRoleIds = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_ROLE_ASSIGNED,
            entityType: 'User',
            entityId: $userId,
            oldValues: $oldRoleIds !== null ? ['role_ids' => $oldRoleIds] : null,
            newValues: ['role_ids' => array_values($roleIds)],
            metadata: ['tenant_id' => $tenantId],
            tenantId: $tenantId
        );
    }

    // ============ Email / Profile Actions ============

    public function logEmailChanged(int $userId, string $oldEmail, string $newEmail): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_EMAIL_CHANGED,
            entityType: 'User',
            entityId: $userId,
            oldValues: ['email' => $oldEmail],
            newValues: ['email' => $newEmail]
        );
    }

    public function logProfileUpdated(int $userId, array $oldData, array $newData): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PROFILE_UPDATED,
            entityType: 'User',
            entityId: $userId,
            oldValues: $oldData,
            newValues: $newData,
            userId: $userId
        );
    }

    public function logDataChangeRequested(int $userId, array $fields): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PROFILE_DATA_CHANGE_REQUESTED,
            entityType: 'User',
            entityId: $userId,
            metadata: ['fields' => $fields],
            userId: $userId
        );
    }

    // ============ User Actions ============

    public function logUserCreated(int $userId, array $userData): ?AuditLog
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

    public function logUserUpdated(int $userId, array $oldData, array $newData): ?AuditLog
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

    public function logUserDeleted(int $userId, array $userData): ?AuditLog
    {
        unset($userData['password']);

        return $this->log(
            action: AuditLog::ACTION_USER_DELETED,
            entityType: 'User',
            entityId: $userId,
            oldValues: $userData
        );
    }

    /**
     * Habilitación (restauración) de una cuenta eliminada — solo root, ver
     * ability 'users.restore'. Espeja a logUserDeleted(): allí los datos del
     * usuario viajan como oldValues (lo que se perdió), aquí como newValues
     * (lo que vuelve a existir), para que el diff del visor de auditoría se
     * lea en la dirección correcta.
     */
    public function logUserRestored(int $userId, array $userData): ?AuditLog
    {
        unset($userData['password']);

        return $this->log(
            action: AuditLog::ACTION_USER_RESTORED,
            entityType: 'User',
            entityId: $userId,
            newValues: $userData
        );
    }

    // ============ Impersonation Actions ============

    /**
     * Root inicia una sesión "iniciar sesión como" otro usuario. El actor es
     * SIEMPRE root (userId explícito): en el momento de esta llamada, root
     * todavía opera con su propia sesión (sin marca), así que el $userId
     * explícito no es opcional aquí como en otros helpers — sin él, log()
     * tomaría el user_id del Auth::user() actual, que también es root, pero
     * dejaría la relación acción→objetivo solo en entityId, menos explícita.
     */
    public function logImpersonationStarted(int $rootId, int $targetUserId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_IMPERSONATION_STARTED,
            entityType: 'User',
            entityId: $targetUserId,
            userId: $rootId
        );
    }

    /**
     * Root sale de una sesión impersonada y recupera la suya. Espejo de
     * logImpersonationStarted(): mismo actor (root) y mismo entityId (el
     * empleado cuya identidad se dejó de usar).
     */
    public function logImpersonationStopped(int $rootId, int $targetUserId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_IMPERSONATION_STOPPED,
            entityType: 'User',
            entityId: $targetUserId,
            userId: $rootId
        );
    }

    // ============ Document Actions ============

    public function logDocumentUploaded(int $documentId, string $filename, ?int $tenantId = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_UPLOADED,
            entityType: 'Document',
            entityId: $documentId,
            metadata: ['filename' => $filename],
            tenantId: $tenantId
        );
    }

    public function logDocumentViewed(int $documentId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_VIEWED,
            entityType: 'Document',
            entityId: $documentId
        );
    }

    public function logDocumentDownloaded(int $documentId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_DOWNLOADED,
            entityType: 'Document',
            entityId: $documentId
        );
    }

    public function logDocumentSigned(int $documentId, int $userId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_SIGNED,
            entityType: 'Document',
            entityId: $documentId,
            userId: $userId
        );
    }

    public function logDocumentDeleted(int $documentId, array $documentData): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_DOCUMENT_DELETED,
            entityType: 'Document',
            entityId: $documentId,
            oldValues: $documentData
        );
    }

    public function logBatchCreated(int $batchId, int $documentCount, ?int $tenantId = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_BATCH_CREATED,
            entityType: 'DocumentBatch',
            entityId: $batchId,
            metadata: ['document_count' => $documentCount],
            tenantId: $tenantId
        );
    }

    public function logBatchCompleted(int $batchId, int $successCount, int $failedCount, ?int $userId = null, ?int $tenantId = null): ?AuditLog
    {
        // userId/tenantId explícitos: este método suele invocarse desde un
        // job encolado (BatchCompletedCallback), donde Auth::user() es null.
        return $this->log(
            action: AuditLog::ACTION_BATCH_COMPLETED,
            entityType: 'DocumentBatch',
            entityId: $batchId,
            metadata: [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ],
            userId: $userId,
            tenantId: $tenantId
        );
    }

    public function logUserBatchCreated(int $batchId, int $count): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_USER_BATCH_CREATED,
            entityType: 'UserBatch',
            entityId: $batchId,
            metadata: ['user_count' => $count]
        );
    }

    public function logUserBatchCompleted(int $batchId, array $summary, ?int $userId = null, ?int $tenantId = null): ?AuditLog
    {
        // [OBS-CLIENTE 2026-08] Huérfano: su único invocador, el job
        // ProcessBulkUserUpload, se eliminó por ser código muerto (nunca se
        // despachaba; el pipeline vivo de carga masiva es Bus::batch +
        // ProcessUserChunk). Se conserva el método -no se borra código sin
        // pedido explícito- pero hoy no lo llama nadie; userId/tenantId
        // quedan explícitos por si se retoma un flujo de "batch completado".
        return $this->log(
            action: AuditLog::ACTION_USER_BATCH_COMPLETED,
            entityType: 'UserBatch',
            entityId: $batchId,
            metadata: $summary,
            userId: $userId,
            tenantId: $tenantId
        );
    }

    // ============ Platform Settings Actions ============

    public function logPlatformSettingsUpdated(array $old, array $new): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_PLATFORM_SETTINGS_UPDATED,
            entityType: 'PlatformSettings',
            oldValues: $old,
            newValues: $new
        );
    }

    // ============ Signature Actions ============

    public function logSignatureSettingsUpdated(int $userId, array $metadata): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_SIGNATURE_SETTINGS_UPDATED,
            entityType: 'SignatureSettings',
            metadata: $metadata,
            userId: $userId
        );
    }

    public function logCertificateUploaded(int $userId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_SIGNATURE_CERT_UPLOADED,
            entityType: 'SignatureSettings',
            userId: $userId
        );
    }

    public function logCertificateDeleted(int $userId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_SIGNATURE_CERT_DELETED,
            entityType: 'SignatureSettings',
            userId: $userId
        );
    }

    public function logTermsAccepted(int $userId): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_SIGNATURE_TERMS_ACCEPTED,
            entityType: 'User',
            entityId: $userId,
            userId: $userId
        );
    }

    // ============ Vacation Actions ============

    public function logVacationRequested(int $vacationId, array $data): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_REQUESTED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            newValues: $data
        );
    }

    public function logVacationApproved(int $vacationId, ?string $comment = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_APPROVED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $comment ? ['comment' => $comment] : null
        );
    }

    public function logVacationRejected(int $vacationId, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_REJECTED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $reason ? ['reason' => $reason] : null
        );
    }

    public function logVacationConfirmed(int $vacationId, bool $wasTaken): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_CONFIRMED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: ['was_taken' => $wasTaken]
        );
    }

    public function logVacationCancelled(int $vacationId, ?string $reason = null): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_VACATION_CANCELLED,
            entityType: 'VacationRequest',
            entityId: $vacationId,
            metadata: $reason ? ['reason' => $reason] : null
        );
    }

    // ============ Tenant Actions ============

    public function logTenantCreated(int $tenantId, array $data): ?AuditLog
    {
        return $this->log(
            action: AuditLog::ACTION_TENANT_CREATED,
            entityType: 'Tenant',
            entityId: $tenantId,
            newValues: $data,
            tenantId: $tenantId
        );
    }

    public function logTenantUpdated(int $tenantId, array $oldData, array $newData): ?AuditLog
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

    public function logTenantDeleted(int $tenantId, array $data): ?AuditLog
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
