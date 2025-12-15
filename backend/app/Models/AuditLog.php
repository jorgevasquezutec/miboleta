<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit Log Model
 * 
 * Stores all important actions performed in the system for auditing purposes.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'action',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // ============ Action Constants ============

    // Authentication
    public const ACTION_USER_LOGIN = 'user.login';
    public const ACTION_USER_LOGOUT = 'user.logout';
    public const ACTION_USER_LOGIN_FAILED = 'user.login_failed';
    public const ACTION_PASSWORD_CHANGED = 'user.password_changed';
    public const ACTION_PASSWORD_RESET = 'user.password_reset';

    // User Management
    public const ACTION_USER_CREATED = 'user.created';
    public const ACTION_USER_UPDATED = 'user.updated';
    public const ACTION_USER_DELETED = 'user.deleted';

    // Document Actions
    public const ACTION_DOCUMENT_UPLOADED = 'document.uploaded';
    public const ACTION_DOCUMENT_VIEWED = 'document.viewed';
    public const ACTION_DOCUMENT_DOWNLOADED = 'document.downloaded';
    public const ACTION_DOCUMENT_SIGNED = 'document.signed';
    public const ACTION_DOCUMENT_DELETED = 'document.deleted';
    public const ACTION_BATCH_CREATED = 'batch.created';
    public const ACTION_BATCH_COMPLETED = 'batch.completed';

    // Vacation Actions
    public const ACTION_VACATION_REQUESTED = 'vacation.requested';
    public const ACTION_VACATION_APPROVED = 'vacation.approved';
    public const ACTION_VACATION_REJECTED = 'vacation.rejected';
    public const ACTION_VACATION_CONFIRMED = 'vacation.confirmed';
    public const ACTION_VACATION_CANCELLED = 'vacation.cancelled';

    // Tenant Actions
    public const ACTION_TENANT_CREATED = 'tenant.created';
    public const ACTION_TENANT_UPDATED = 'tenant.updated';
    public const ACTION_TENANT_DELETED = 'tenant.deleted';

    // ============ Relationships ============

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // ============ Scopes ============

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeOfAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeOfEntity($query, string $entityType, ?int $entityId = null)
    {
        $query->where('entity_type', $entityType);

        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        return $query;
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ============ Helpers ============

    /**
     * Get the entity model if it exists.
     */
    public function getEntity(): ?Model
    {
        if (!$this->entity_type || !$this->entity_id) {
            return null;
        }

        $entityClass = 'App\\Models\\' . $this->entity_type;

        if (!class_exists($entityClass)) {
            return null;
        }

        return $entityClass::find($this->entity_id);
    }

    /**
     * Get a human-readable description of the action.
     */
    public function getDescriptionAttribute(): string
    {
        $descriptions = [
            self::ACTION_USER_LOGIN => 'Inició sesión',
            self::ACTION_USER_LOGOUT => 'Cerró sesión',
            self::ACTION_USER_LOGIN_FAILED => 'Intento de inicio de sesión fallido',
            self::ACTION_PASSWORD_CHANGED => 'Cambió su contraseña',
            self::ACTION_PASSWORD_RESET => 'Restableció contraseña',
            self::ACTION_USER_CREATED => 'Creó un usuario',
            self::ACTION_USER_UPDATED => 'Actualizó un usuario',
            self::ACTION_USER_DELETED => 'Eliminó un usuario',
            self::ACTION_DOCUMENT_UPLOADED => 'Cargó un documento',
            self::ACTION_DOCUMENT_VIEWED => 'Visualizó un documento',
            self::ACTION_DOCUMENT_DOWNLOADED => 'Descargó un documento',
            self::ACTION_DOCUMENT_SIGNED => 'Firmó un documento',
            self::ACTION_DOCUMENT_DELETED => 'Eliminó un documento',
            self::ACTION_BATCH_CREATED => 'Creó un lote de documentos',
            self::ACTION_BATCH_COMPLETED => 'Completó un lote de documentos',
            self::ACTION_VACATION_REQUESTED => 'Solicitó vacaciones',
            self::ACTION_VACATION_APPROVED => 'Aprobó vacaciones',
            self::ACTION_VACATION_REJECTED => 'Rechazó vacaciones',
            self::ACTION_VACATION_CONFIRMED => 'Confirmó vacaciones tomadas',
            self::ACTION_VACATION_CANCELLED => 'Canceló vacaciones',
            self::ACTION_TENANT_CREATED => 'Creó una organización',
            self::ACTION_TENANT_UPDATED => 'Actualizó una organización',
            self::ACTION_TENANT_DELETED => 'Eliminó una organización',
        ];

        return $descriptions[$this->action] ?? $this->action;
    }

    /**
     * Get the action category.
     */
    public function getCategoryAttribute(): string
    {
        $parts = explode('.', $this->action);
        return $parts[0] ?? 'other';
    }
}
