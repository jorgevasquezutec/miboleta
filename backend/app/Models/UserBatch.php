<?php

namespace App\Models;

use App\Models\Scopes\TenantFilterScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserBatch extends Model
{
    protected $fillable = [
        'uuid',
        'tenant_id',
        'created_by_user_id',
        'original_filename',
        'file_path',
        'file_size',
        'status',
        'total_rows',
        'processed_rows',
        'created_users',
        'updated_users',
        'failed_rows',
        'current_chunk',
        'total_chunks',
        'progress_percentage',
        'error_summary',
        'success_summary',
        'processing_options',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'created_users' => 'integer',
        'updated_users' => 'integer',
        'failed_rows' => 'integer',
        'current_chunk' => 'integer',
        'total_chunks' => 'integer',
        'progress_percentage' => 'decimal:2',
        'error_summary' => 'array',
        'success_summary' => 'array',
        'processing_options' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        // Auto-generar UUID al crear
        static::creating(function ($batch) {
            if (!$batch->uuid) {
                $batch->uuid = Str::uuid();
            }
        });

        // ✅ Aplicar TenantFilterScope para multi-tenant isolation
        static::addGlobalScope(new TenantFilterScope());
    }

    // ────────────────────────────────────────────────────────────
    // RELACIONES
    // ────────────────────────────────────────────────────────────

    /**
     * Organización a la que pertenece este batch
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Usuario que inició la carga masiva
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ────────────────────────────────────────────────────────────
    // MÉTODOS DE ESTADO
    // ────────────────────────────────────────────────────────────

    /**
     * Verificar si el batch está en procesamiento
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Verificar si el batch ha completado (con o sin errores)
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['completed', 'partial']);
    }

    /**
     * Verificar si el batch falló completamente
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verificar si está pendiente de procesar
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    // ────────────────────────────────────────────────────────────
    // MÉTODOS DE ACTUALIZACIÓN
    // ────────────────────────────────────────────────────────────

    /**
     * Actualizar progreso del batch durante procesamiento
     */
    public function updateProgress(array $data): void
    {
        $this->update([
            'current_chunk' => $data['chunk'] ?? $this->current_chunk,
            'processed_rows' => $data['processed'] ?? $this->processed_rows,
            'created_users' => $data['created'] ?? $this->created_users,
            'updated_users' => $data['updated'] ?? $this->updated_users,
            'failed_rows' => $data['failed'] ?? $this->failed_rows,
            'progress_percentage' => $data['percentage'] ?? $this->progress_percentage,
            'status' => 'processing',
        ]);
    }

    /**
     * Marcar batch como completado
     */
    public function markAsCompleted(array $summary): void
    {
        $this->update([
            'status' => $summary['failed_rows'] > 0 ? 'partial' : 'completed',
            'completed_at' => now(),
            'success_summary' => $summary,
            'progress_percentage' => 100,
        ]);
    }

    /**
     * Marcar batch como fallido
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_summary' => ['message' => $error],
        ]);
    }

    /**
     * Iniciar procesamiento del batch
     */
    public function start(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // ACCESSORS
    // ────────────────────────────────────────────────────────────

    /**
     * Obtener duración del procesamiento en segundos
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }

    /**
     * Obtener variant de badge según status
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'secondary',
            'processing' => 'warning',
            'completed' => 'success',
            'failed' => 'danger',
            'partial' => 'info',
            default => 'secondary',
        };
    }

    /**
     * Obtener texto legible del status
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pendiente',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Fallido',
            'partial' => 'Parcial',
            default => 'Desconocido',
        };
    }

    /**
     * Verificar si tiene errores para descargar
     */
    public function hasErrors(): bool
    {
        return $this->failed_rows > 0 && !empty($this->error_summary);
    }

    /**
     * Obtener porcentaje formateado
     */
    public function getFormattedProgressAttribute(): string
    {
        return number_format((float) $this->progress_percentage, 1) . '%';
    }

    // ────────────────────────────────────────────────────────────
    // SCOPES
    // ────────────────────────────────────────────────────────────

    /**
     * Scope para batches en procesamiento
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope para batches completados
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['completed', 'partial']);
    }

    /**
     * Scope para batches fallidos
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope para batches recientes
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
