<?php

namespace App\Jobs;

use App\Models\UserBatch;
use App\Services\BulkUserUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBulkUserUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutos máximo
    public $tries = 1; // No reintentar automáticamente

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $batchUuid,
        private array $users
    ) {
        // Queue específica para bulk uploads
        $this->onQueue('bulk-uploads');
    }

    /**
     * Execute the job.
     */
    public function handle(BulkUserUploadService $service): void
    {
        // Buscar el batch en BD
        $batch = UserBatch::where('uuid', $this->batchUuid)->firstOrFail();

        Log::info("🚀 [BulkUserUpload] Starting batch processing", [
            'batch_uuid' => $this->batchUuid,
            'total_users' => count($this->users),
        ]);

        // Iniciar procesamiento
        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
            'total_chunks' => ceil(count($this->users) / 50),
        ]);

        try {
            // Procesar usuarios en chunks usando Generator
            foreach ($service->processUsersInChunks($this->users, 50) as $progress) {

                if ($progress['type'] === 'progress') {
                    // ✅ Actualizar progreso en BD
                    $batch->updateProgress($progress);

                    Log::debug("[BulkUserUpload] Progress update", [
                        'batch_uuid' => $this->batchUuid,
                        'chunk' => $progress['chunk'],
                        'percentage' => $progress['percentage'],
                    ]);

                } elseif ($progress['type'] === 'error') {
                    // ❌ Error en chunk específico
                    Log::error("[BulkUserUpload] Chunk error", [
                        'batch_uuid' => $this->batchUuid,
                        'chunk' => $progress['chunk'],
                        'error' => $progress['message'],
                    ]);

                } elseif ($progress['type'] === 'complete') {
                    // ✅ Procesamiento completado
                    $summary = $progress['summary'];

                    $batch->markAsCompleted($summary);

                    Log::info("✅ [BulkUserUpload] Batch completed successfully", [
                        'batch_uuid' => $this->batchUuid,
                        'created' => $summary['created'],
                        'updated' => $summary['updated'],
                        'failed' => $summary['failed_rows'],
                        'duration' => $batch->duration . 's',
                    ]);

                    // Notificar al usuario que creó el batch
                    // $batch->createdBy->notify(new BulkUploadCompleted($batch));
                }
            }

        } catch (\Exception $e) {
            // Error fatal en el procesamiento
            $batch->markAsFailed($e->getMessage());

            Log::error("❌ [BulkUserUpload] Batch failed with exception", [
                'batch_uuid' => $this->batchUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw para marcar el job como fallido
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $batch = UserBatch::where('uuid', $this->batchUuid)->first();

        if ($batch) {
            $batch->markAsFailed($exception->getMessage());
        }

        Log::critical("💥 [BulkUserUpload] Job failed permanently", [
            'batch_uuid' => $this->batchUuid,
            'exception' => $exception->getMessage(),
        ]);

        // Opcional: Notificar al admin
        // Mail::to(config('app.admin_email'))->send(new BulkUploadJobFailed($batch));
    }
}
