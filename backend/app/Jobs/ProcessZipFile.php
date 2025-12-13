<?php

namespace App\Jobs;

use App\Models\DocumentBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcessZipFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutos

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DocumentBatch $batch,
        public string $zipPath
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessZipFile: Iniciando procesamiento del batch {$this->batch->id}");

        $this->batch->markAsStarted();

        try {
            $zip = new ZipArchive();
            $fullPath = Storage::disk('local')->path($this->zipPath);

            if ($zip->open($fullPath) !== true) {
                throw new \Exception('No se pudo abrir el archivo ZIP');
            }

            // Recolectar archivos válidos
            $validFiles = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $filename = basename($stat['name']);

                // Ignorar directorios y archivos ocultos
                if (str_ends_with($stat['name'], '/') || str_starts_with($filename, '.')) {
                    continue;
                }

                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($extension !== 'pdf') {
                    $this->batch->addError($filename, 'Formato no válido (solo PDF)');
                    continue;
                }

                $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
                if (!preg_match('/^\d{8,11}$/', $nameWithoutExt)) {
                    $this->batch->addError($filename, 'Nombre de archivo debe ser un DNI válido (8-11 dígitos)');
                    continue;
                }

                $validFiles[] = [
                    'index' => $i,
                    'filename' => $filename,
                    'document_number' => $nameWithoutExt,
                    'size' => $stat['size'],
                ];
            }

            // Actualizar total de archivos
            $this->batch->update(['total_files' => count($validFiles)]);

            if (empty($validFiles)) {
                $zip->close();
                $this->cleanup();
                $this->batch->markAsFailed('No se encontraron archivos PDF válidos');
                return;
            }

            // Procesar en chunks de 50
            $chunks = array_chunk($validFiles, 50);
            $jobs = [];

            foreach ($chunks as $chunkIndex => $chunk) {
                $jobs[] = new ProcessDocumentChunk(
                    $this->batch,
                    $this->zipPath,
                    $chunk
                );
            }

            $zip->close();

            // ⭐ Usar Laravel Batches nativo con callbacks invocables
            $laravelBatch = Bus::batch($jobs)
                ->name("Batch #{$this->batch->id} - {$this->batch->period}")
                ->then(new \App\Jobs\Callbacks\BatchCompletedCallback($this->batch))
                ->catch(new \App\Jobs\Callbacks\BatchFailedCallback($this->batch))
                ->finally(new \App\Jobs\Callbacks\BatchFinallyCallback($this->zipPath))
                ->onQueue('documents')
                ->dispatch();

            // Guardar el ID del batch de Laravel
            $this->batch->update(['laravel_batch_id' => $laravelBatch->id]);

            Log::info("ProcessZipFile: Laravel Batch {$laravelBatch->id} despachado con " . count($jobs) . " jobs");

        } catch (\Exception $e) {
            Log::error("ProcessZipFile: Error procesando batch {$this->batch->id}: {$e->getMessage()}");
            $this->batch->markAsFailed($e->getMessage());
            $this->cleanup(); // Keep cleanup here for initial setup failures before batch dispatch
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessZipFile: Job fallido para batch {$this->batch->id}: {$exception->getMessage()}");
        $this->batch->markAsFailed($exception->getMessage());
        $this->cleanup();
    }

    /**
     * Limpiar archivo temporal
     */
    private function cleanup(): void
    {
        try {
            Storage::disk('local')->delete($this->zipPath);
        } catch (\Exception $e) {
            Log::warning("ProcessZipFile: No se pudo eliminar archivo temporal: {$this->zipPath}");
        }
    }
}
