<?php

namespace App\Models;

use App\Models\Scopes\TenantFilterScope;
use App\Models\Tenant;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
//log
use Illuminate\Support\Facades\Log;

class UserBatch extends Model
{
    protected $fillable = [
        'uuid',
        'batch_id',
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

    protected $hidden = [
        'updated_users', // No usamos actualización de usuarios en bulk uploads
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

    // ────────────────────────────────────────────────────────────
    // LARAVEL BUS BATCH TRACKING
    // ────────────────────────────────────────────────────────────

    /**
     * Obtener el Laravel Bus Batch asociado
     */
    public function laravelBatch(): ?Batch
    {
        if (!$this->batch_id) {
            return null;
        }

        return Bus::findBatch($this->batch_id);
    }

    /**
     * Obtener progreso en tiempo real del batch de Laravel
     */
    public function getBatchProgressAttribute(): ?array
    {
        $laravelBatch = $this->laravelBatch();

        if (!$laravelBatch) {
            return null;
        }

        return [
            'total_jobs' => $laravelBatch->totalJobs,
            'pending_jobs' => $laravelBatch->pendingJobs,
            'processed_jobs' => $laravelBatch->processedJobs(),
            'failed_jobs' => $laravelBatch->failedJobs,
            'progress_percentage' => $laravelBatch->progress(),
            'finished' => $laravelBatch->finished(),
            'cancelled' => $laravelBatch->cancelled(),
        ];
    }

    /**
     * Cancelar el batch en ejecución
     */
    public function cancelBatch(): bool
    {
        $laravelBatch = $this->laravelBatch();

        if ($laravelBatch && !$laravelBatch->finished()) {
            $laravelBatch->cancel();

            $this->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_summary' => ['message' => 'Batch cancelado por el usuario'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Registrar los tenants afectados por este batch (unión acumulada entre
     * chunks). Se guarda dentro de processing_options para no requerir una
     * columna nueva. Es "mejor esfuerzo": bajo alta concurrencia entre
     * chunks del mismo batch podría perderse algún id (read-modify-write no
     * atómico), aceptable dado el volumen bajo de chunks concurrentes.
     */
    public function mergeAffectedTenantIds(array $tenantIds): void
    {
        $tenantIds = array_values(array_unique(array_filter(array_map('intval', $tenantIds))));

        if (empty($tenantIds)) {
            return;
        }

        $this->refresh();
        $options = $this->processing_options ?? [];
        $existing = $options['affected_tenant_ids'] ?? [];
        $options['affected_tenant_ids'] = array_values(array_unique(array_merge($existing, $tenantIds)));

        $this->update(['processing_options' => $options]);
    }

    /**
     * Fijar el conteo inicial de empleados (tenants.initial_employee_count)
     * para cada tenant afectado por este batch, de forma idempotente: solo
     * si aún no tiene un valor (0/null). Se invoca al completar el batch.
     */
    public function syncInitialEmployeeCounts(): void
    {
        $tenantIds = $this->processing_options['affected_tenant_ids'] ?? [];

        if (empty($tenantIds)) {
            return;
        }

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::find($tenantId);

            if (!$tenant || !empty($tenant->initial_employee_count)) {
                continue; // tenant inexistente o ya fijado: no tocar (idempotente)
            }

            $currentCount = $tenant->users()->count();
            $tenant->update(['initial_employee_count' => $currentCount]);

            Log::info('📊 [BulkUserUpload] initial_employee_count fijado', [
                'tenant_id' => $tenant->id,
                'initial_employee_count' => $currentCount,
                'batch_uuid' => $this->uuid,
            ]);
        }
    }

    /**
     * Callback cuando el batch se completa exitosamente
     */
    public static function onBatchCompleted(int $batchId): void
    {
        $batch = self::where('id', $batchId)->first();

        if ($batch) {
            $batch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'progress_percentage' => 100,
            ]);

            $batch->syncInitialEmployeeCounts();

            Log::info("✅ [BulkUserUpload] Batch completed", [
                'batch_uuid' => $batch->uuid,
                'batch_id' => $batch->batch_id,
            ]);
        }
    }

    /**
     * Callback cuando el batch falla
     */
    public static function onBatchFailed(int $batchId, string $error): void
    {
        $batch = self::where('id', $batchId)->first();
        
        if ($batch) {
            Log::error("❌ [BulkUserUpload] Batch failed", [
                'batch_uuid' => $batch->uuid,
                'batch_id' => $batch->batch_id,
                'error' => $error,
            ]);
        }
    }

    /**
     * Callback cuando el batch finaliza (éxito o fallo)
     */
    public static function onBatchFinished(int $batchId, int $failedJobs): void
    {
        $batch = self::where('id', $batchId)->first();

        if ($batch && !$batch->completed_at) {
            $batch->update([
                'status' => $failedJobs > 0 ? 'partial' : 'completed',
                'completed_at' => now(),
            ]);

            // Best-effort e idempotente: fija el contador de empleados aun
            // si algún chunk falló por completo (then() no se ejecuta en ese caso).
            $batch->syncInitialEmployeeCounts();

            Log::info("🏁 [BulkUserUpload] Batch finished", [
                'batch_uuid' => $batch->uuid,
                'batch_id' => $batch->batch_id,
                'failed_jobs' => $failedJobs,
            ]);
        }
    }
}
