<?php

namespace App\Jobs;

use App\Events\BatchProgress;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Services\DocumentSigningService;
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

        $signingService = app(DocumentSigningService::class);

        $persist = function () use ($content, $storagePath, $file, $documentNumber, $user, $isOrphan, $existingDocument, $isReplacement, $requiresSignature) {
            $disk = Storage::disk('documents');

            if ($isReplacement) {
                // La fila se leyó ANTES de tomar el lock: un cierre de
                // SignDocument o una conformidad pudieron escribirla en
                // medio. Se vuelve a leer ahora (bajo el lock) para que el
                // diff de Eloquent compare contra el estado real y los null
                // / false de abajo siempre se escriban.
                $existingDocument->refresh();

                // El signer corre como root y deja el archivo firmado 0644
                // root: un file_put_contents encima fallaría con EACCES para
                // www-data. Se borra primero (el directorio es de www-data).
                if ($existingDocument->file_path === $storagePath && $disk->exists($storagePath)) {
                    $disk->delete($storagePath);
                }
            }

            // Guardar archivo
            $disk->put($storagePath, $content);

            $status = $isOrphan ? 'orphan' : ($requiresSignature ? 'pending' : 'active');

            if ($isReplacement) {
                // La base de re-firma del archivo anterior ya no sirve: se
                // borra junto con TODA la firma digital (el job SignDocument
                // en vuelo descarta su resultado al ver otra `version`).
                $oldOriginal = $existingDocument->original_file_path;
                if ($oldOriginal && Storage::disk('documents')->exists($oldOriginal)) {
                    Storage::disk('documents')->delete($oldOriginal);
                }

                // Actualizar documento existente
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
                    'digital_signature' => null,
                    'digitally_signed_at' => null,
                    'digital_signature_status' => null,
                    'digital_signature_error' => null,
                    'original_file_path' => null,
                    'original_has_conformity' => false,
                    'notified' => false,
                    'notified_at' => null,
                    'version' => $existingDocument->version + 1,
                ]);

                return $existingDocument;
            }

            // Crear nuevo documento
            return Document::create([
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
        };

        // Un reemplazo escribe file_path / original_* / digital_*: va bajo el
        // lock por documento (ver DocumentSigningService).
        $document = $isReplacement
            ? $signingService->withDocumentLock($existingDocument->id, fn () => DB::transaction($persist))
            : DB::transaction($persist);

        // Actualizar contadores del batch
        $this->batch->incrementProcessed(
            success: true,
            replaced: $isReplacement,
            orphan: $isOrphan
        );

        // Firma digital automática: si el documento quedó 'pending' (no
        // huérfano y requiere firma) y le aplica el pipeline PAdES (firma de
        // plataforma activada con certificado vigente), se marca
        // digital_signature_status='pending' y se encola SignDocument. Si no
        // aplica, el documento queda 'pending' como hasta ahora (firmable
        // luego on-demand vía POST /documents/{id}/sign-digital, o vía el
        // flujo de 2FA de email existente).
        if ($document->status === 'pending' && $signingService->appliesTo($document)) {
            $signingService->markPendingAndDispatch($document, 'signing');
        }
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
