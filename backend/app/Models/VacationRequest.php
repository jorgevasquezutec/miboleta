<?php

namespace App\Models;

use App\Models\Scopes\TenantFilterScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $tenant_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property float $days_requested
 * @property string|null $reason
 * @property string $status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property bool|null $was_taken
 * @property int|null $confirmed_by
 * @property Carbon|null $confirmed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Tenant $tenant
 * @property-read User|null $approvedByUser
 * @property-read User|null $rejectedByUser
 * @property-read User|null $confirmedByUser
 * @property-read string $status_label
 * @property-read string|null $taken_label
 * @property-read string $duration_text
 * @property-read string $date_range
 */
class VacationRequest extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        // ✅ Aplicar filtro automático de tenant
        static::addGlobalScope(new TenantFilterScope);
    }


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
     * 
     * @deprecated This scope is now handled automatically by TenantFilterScope global scope.
     *             The global scope automatically filters by tenant_id based on X-Tenant-Ids header.
     *             Keeping this for backward compatibility but should not be used.
     */
    public function scopeForTenant($query, int $tenantId)
    {
        // ⚠️ DEPRECATED: Global scope TenantFilterScope handles this automatically
        // Do nothing - global scope will apply the filter
        return $query;
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
     * Uses the user_tenants pivot table where supervisor_id is stored.
     */
    public function scopeForSupervisor($query, int $supervisorId)
    {
        return $query->whereHas('user', function ($q) use ($supervisorId) {
            // Supervisor relationship is in user_tenants pivot table
            $q->whereHas('tenants', function ($tenantQuery) use ($supervisorId) {
                $tenantQuery->where('user_tenants.supervisor_id', $supervisorId);
            });
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
        $days = (float) $this->days_requested;

        if ($days == 1) {
            return '1 día';
        } elseif ($days == 0.5) {
            return 'Medio día';
        } else {
            // Remove unnecessary decimals
            $daysFormatted = $days == floor($days) ? (int) $days : $days;
            return "{$daysFormatted} días";
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
