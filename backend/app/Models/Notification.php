<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    // Notification types
    public const TYPE_DOCUMENT_NEW = 'document.new';
    public const TYPE_DOCUMENT_SIGNED = 'document.signed';
    public const TYPE_VACATION_CREATED = 'vacation.created';
    public const TYPE_VACATION_APPROVED = 'vacation.approved';
    public const TYPE_VACATION_REJECTED = 'vacation.rejected';
    public const TYPE_VACATION_PENDING_CONFIRMATION = 'vacation.pending_confirmation';
    public const TYPE_VACATION_TAKEN = 'vacation.taken';
    public const TYPE_VACATION_NOT_TAKEN = 'vacation.not_taken';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'type',
        'title',
        'message',
        'data',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

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

    /**
     * Scope to get notifications for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get notifications for specific tenant(s).
     */
    public function scopeForTenant($query, int|array $tenantIds)
    {
        if (is_array($tenantIds)) {
            return $query->whereIn('tenant_id', $tenantIds);
        }
        return $query->where('tenant_id', $tenantIds);
    }

    /**
     * Scope to get unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get read notifications.
     */
    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ============ Helpers ============

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Get human-readable type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DOCUMENT_NEW => 'Nuevo documento',
            self::TYPE_DOCUMENT_SIGNED => 'Documento firmado',
            self::TYPE_VACATION_CREATED => 'Solicitud de vacaciones',
            self::TYPE_VACATION_APPROVED => 'Vacaciones aprobadas',
            self::TYPE_VACATION_REJECTED => 'Vacaciones rechazadas',
            self::TYPE_VACATION_PENDING_CONFIRMATION => 'Vacaciones por confirmar',
            self::TYPE_VACATION_TAKEN => 'Vacaciones confirmadas',
            self::TYPE_VACATION_NOT_TAKEN => 'Vacaciones no tomadas',
            default => 'Notificación',
        };
    }

    /**
     * Get icon name based on type.
     */
    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DOCUMENT_NEW => 'file-text',
            self::TYPE_DOCUMENT_SIGNED => 'file-check',
            self::TYPE_VACATION_CREATED => 'calendar-plus',
            self::TYPE_VACATION_APPROVED => 'calendar-check',
            self::TYPE_VACATION_REJECTED => 'calendar-x',
            self::TYPE_VACATION_PENDING_CONFIRMATION => 'calendar-clock',
            self::TYPE_VACATION_TAKEN => 'check-circle',
            self::TYPE_VACATION_NOT_TAKEN => 'x-circle',
            default => 'bell',
        };
    }
}
