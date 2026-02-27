<?php

namespace App\Jobs;

use App\Events\BatchProgress;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\User;
use Illuminate\Bus\Batchable;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutos

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DocumentBatch $batch,
        public string $zipPath,
        public array $files
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
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

        // Laravel Batches maneja automáticamente la finalización
        // cuando todos los jobs están completados (callback 'then')
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

        // Verificar si ya existe un documento con los mismos datos (incluidos soft-deleted)
        $existingDocument = Document::withTrashed()
            ->where('tenant_id', $this->batch->tenant_id)
            ->where('doc_type_id', $this->batch->type_id)
            ->where('period', $this->batch->period)
            ->where('employee_document_number', $documentNumber)
            ->first();

        // Si el documento fue soft-deleted, restaurarlo
        if ($existingDocument && $existingDocument->trashed()) {
            $existingDocument->restore();
        }

        $isReplacement = $existingDocument !== null;

        // Combine batch-level and document-type-level requires_signature
        $requiresSignature = $this->batch->requires_signature ||
                             ($this->batch->documentType->requires_signature ?? false);

        DB::transaction(function () use ($content, $storagePath, $file, $documentNumber, $user, $isOrphan, $existingDocument, $isReplacement, $requiresSignature) {
            // Guardar archivo
            Storage::disk('documents')->put($storagePath, $content);

            if ($isReplacement) {
                // Actualizar documento existente
                $status = $isOrphan ? 'orphan' : ($requiresSignature ? 'pending' : 'active');
                $existingDocument->update([
                    'batch_id' => $this->batch->id,
                    'user_id' => $user?->id,
                    'file_path' => $storagePath,
                    'file_size' => $file['size'],
                    'original_name' => $file['filename'],
                    'status' => $status,
                    'requires_signature' => $requiresSignature,
                    'signature' => null,
                    'signed_at' => null,
                    'notified' => false,
                    'notified_at' => null,
                    'version' => $existingDocument->version + 1,
                ]);
            } else {
                // Crear nuevo documento
                $status = $isOrphan ? 'orphan' : ($requiresSignature ? 'pending' : 'active');
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
                    'status' => $status,
                    'uploaded_by' => $this->batch->uploaded_by,
                    'requires_signature' => $requiresSignature,
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
    }

    // finalizeBatch() method removed - now handled by BatchCompletedCallback

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessDocumentChunk: Job fallido para batch {$this->batch->id}: {$exception->getMessage()}");

        // Laravel Batches 'catch' callback maneja el mark as failed
    }
}
