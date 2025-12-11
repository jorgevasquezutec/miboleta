<?php

namespace App\Jobs;

use App\Events\BatchCompleted;
use App\Events\BatchProgress;
use App\Events\NewDocumentAvailable;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ProcessDocumentChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutos

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DocumentBatch $batch,
        public string $zipPath,
        public array $files,
        public bool $isLastChunk = false
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessDocumentChunk: Procesando " . count($this->files) . " archivos para batch {$this->batch->id}");

        $zip = new ZipArchive();
        $fullPath = Storage::disk('local')->path($this->zipPath);

        if ($zip->open($fullPath) !== true) {
            throw new \Exception('No se pudo abrir el archivo ZIP');
        }

        foreach ($this->files as $file) {
            try {
                $this->processFile($zip, $file);
            } catch (\Exception $e) {
                Log::error("ProcessDocumentChunk: Error procesando {$file['filename']}: {$e->getMessage()}");
                $this->batch->addError($file['filename'], $e->getMessage());
                $this->batch->incrementProcessed(success: false);
            }
        }

        $zip->close();

        // Broadcast progreso del batch
        broadcast(new BatchProgress($this->batch->fresh()));

        // Si es el último chunk, finalizar el batch
        if ($this->isLastChunk) {
            $this->finalizeBatch();
        }
    }

    /**
     * Procesar un archivo individual
     */
    private function processFile(ZipArchive $zip, array $file): void
    {
        $documentNumber = $file['document_number'];

        // Buscar usuario por número de documento
        $user = User::where('document_text', $documentNumber)
            ->whereHas('tenants', function ($q) {
                $q->where('tenants.id', $this->batch->tenant_id);
            })
            ->first();

        $isOrphan = $user === null;

        // Definir la ruta de almacenamiento
        // Estructura: {tenant_id}/{doc_type_name}/{period}/{document_number}.pdf
        $docType = $this->batch->documentType;
        $storagePath = sprintf(
            '%s/%s/%s/%s.pdf',
            $this->batch->tenant_id,
            $docType->name,
            $this->batch->period,
            $documentNumber
        );

        // Extraer contenido del archivo
        $content = $zip->getFromIndex($file['index']);
        if ($content === false) {
            throw new \Exception('No se pudo leer el contenido del archivo');
        }

        // Verificar si ya existe un documento con los mismos datos
        $existingDocument = Document::where('tenant_id', $this->batch->tenant_id)
            ->where('doc_type_id', $this->batch->type_id)
            ->where('period', $this->batch->period)
            ->where('employee_document_number', $documentNumber)
            ->first();

        $isReplacement = $existingDocument !== null;

        DB::transaction(function () use ($content, $storagePath, $file, $documentNumber, $user, $isOrphan, $existingDocument, $isReplacement) {
            // Guardar archivo
            Storage::disk('documents')->put($storagePath, $content);

            if ($isReplacement) {
                // Actualizar documento existente
                $existingDocument->update([
                    'batch_id' => $this->batch->id,
                    'user_id' => $user?->id,
                    'file_path' => $storagePath,
                    'file_size' => $file['size'],
                    'original_name' => $file['filename'],
                    'status' => $isOrphan ? 'orphan' : 'pending',
                    'requires_signature' => $this->batch->requires_signature,
                    'signature' => null,
                    'signed_at' => null,
                    'notified' => false,
                    'notified_at' => null,
                    'version' => $existingDocument->version + 1,
                ]);
            } else {
                // Crear nuevo documento
                Document::create([
                    'tenant_id' => $this->batch->tenant_id,
                    'user_id' => $user?->id,
                    'batch_id' => $this->batch->id,
                    'doc_type_id' => $this->batch->type_id,
                    'employee_document_number' => $documentNumber,
                    'period' => $this->batch->period,
                    'file_path' => $storagePath,
                    'file_size' => $file['size'],
                    'original_name' => $file['filename'],
                    'status' => $isOrphan ? 'orphan' : 'pending',
                    'uploaded_by' => $this->batch->uploaded_by,
                    'requires_signature' => $this->batch->requires_signature,
                    'expires_at' => now()->addDays(30), // Expira en 30 días
                ]);
            }
        });

        // Actualizar contadores del batch
        $this->batch->incrementProcessed(
            success: true,
            replaced: $isReplacement,
            orphan: $isOrphan
        );

        Log::info("ProcessDocumentChunk: Procesado {$file['filename']} - " .
            ($isReplacement ? 'REEMPLAZADO' : 'NUEVO') . ' - ' .
            ($isOrphan ? 'HUÉRFANO' : 'ASIGNADO'));
    }

    /**
     * Finalizar el batch y enviar notificaciones si es necesario
     */
    private function finalizeBatch(): void
    {
        $this->batch->markAsCompleted();

        // Broadcast evento de completado
        broadcast(new BatchCompleted($this->batch->fresh()));

        // Limpiar archivo ZIP temporal
        try {
            Storage::disk('local')->delete($this->zipPath);
        } catch (\Exception $e) {
            Log::warning("ProcessDocumentChunk: No se pudo eliminar archivo temporal: {$this->zipPath}");
        }

        // Si se debe notificar a empleados, disparar job de notificaciones
        if ($this->batch->notify_employees) {
            SendBatchNotifications::dispatch($this->batch)->onQueue('notifications');
        }

        // Broadcast eventos individuales a cada usuario con documento nuevo
        $documents = Document::where('batch_id', $this->batch->id)
            ->where('status', '!=', 'orphan')
            ->whereNotNull('user_id')
            ->with('documentType')
            ->get();

        foreach ($documents as $document) {
            broadcast(new NewDocumentAvailable($document));
        }

        Log::info("ProcessDocumentChunk: Batch {$this->batch->id} completado - " .
            "Éxito: {$this->batch->success_count}, " .
            "Reemplazados: {$this->batch->replaced_count}, " .
            "Huérfanos: {$this->batch->orphan_count}, " .
            "Errores: {$this->batch->error_count}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDocumentChunk: Job fallido para batch {$this->batch->id}: {$exception->getMessage()}");

        // Si es el último chunk, marcar batch como fallido
        if ($this->isLastChunk) {
            $this->batch->markAsFailed($exception->getMessage());
        }
    }
}
