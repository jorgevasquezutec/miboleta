<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VacationRequest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'start_date',
        'end_date',
        'days_requested',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'was_taken',
        'confirmed_by',
        'confirmed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_requested' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'was_taken' => 'boolean',
    ];

    /**
     * Status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    // ==================== RELATIONSHIPS ====================

    /**
     * Get the user (employee) who requested the vacation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who approved the request.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the request.
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who confirmed if vacation was taken.
     */
    public function confirmedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    // ==================== SCOPES ====================

    /**
     * Scope a query to only include pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope a query to only include rejected requests.
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope a query to only include requests for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to only include requests for a specific tenant.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to only include approved requests pending confirmation.
     */
    public function scopePendingConfirmation($query)
    {
        return $query->where('status', self::STATUS_APPROVED)
            ->whereNull('was_taken');
    }

    /**
     * Scope a query for supervisor's subordinates.
     */
    public function scopeForSupervisor($query, int $supervisorId)
    {
        return $query->whereHas('user', function ($q) use ($supervisorId) {
            $q->where('immediate_supervisor_id', $supervisorId);
        });
    }

    // ==================== METHODS ====================

    /**
     * Check if the request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the request is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the request is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the request is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Approve the vacation request.
     */
    public function approve(User $approver): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject the vacation request.
     */
    public function reject(User $rejector, ?string $reason = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_REJECTED,
            'rejected_by' => $rejector->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Cancel the vacation request (by employee).
     */
    public function cancel(): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Mark vacation as taken (by supervisor).
     */
    public function markAsTaken(User $confirmer): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        return $this->update([
            'was_taken' => true,
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Mark vacation as NOT taken (by supervisor).
     */
    public function markAsNotTaken(User $confirmer): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        return $this->update([
            'was_taken' => false,
            'confirmed_by' => $confirmer->id,
            'confirmed_at' => now(),
        ]);
    }

    // ==================== ACCESSORS ====================

    /**
     * Get the status label in Spanish.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobada',
            self::STATUS_REJECTED => 'Rechazada',
            self::STATUS_CANCELLED => 'Cancelada',
            default => 'Desconocido',
        };
    }

    /**
     * Get the taken status label.
     */
    public function getTakenLabelAttribute(): ?string
    {
        if ($this->was_taken === null) {
            return null;
        }

        return $this->was_taken ? 'Tomada' : 'No tomada';
    }

    /**
     * Get a readable duration text.
     */
    public function getDurationTextAttribute(): string
    {
        $days = $this->days_requested;

        if ($days == 1) {
            return '1 día';
        } elseif ($days == 0.5) {
            return 'Medio día';
        } else {
            return "{$days} días";
        }
    }

    /**
     * Get formatted date range.
     */
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date->format('d/m/Y');
        $end = $this->end_date->format('d/m/Y');

        if ($start === $end) {
            return $start;
        }

        return "{$start} - {$end}";
    }
}
