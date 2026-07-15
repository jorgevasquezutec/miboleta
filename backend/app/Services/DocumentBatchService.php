<?php

namespace App\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Jobs\ProcessZipFile;
use App\Models\DocumentBatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DocumentBatchService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Get batches with filters based on user role.
     *
     * @param User $user
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getBatches(User $user, array $filters = []): LengthAwarePaginator
    {
        $tenantId = $filters['tenant_id'] ?? $user->tenants->first()?->id;

        $query = DocumentBatch::with(['documentType:id,name,display_name', 'uploader:id,name,last_name'])
            ->orderBy('created_at', 'desc');

        // isRoot() en vez de getCurrentRole() !== 'root': determinístico y sin
        // depender del respaldo global de roles.
        if ($tenantId && !$user->isRoot()) {
            $query->where('tenant_id', $tenantId);
        }

        // Optional filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['type_id'])) {
            $query->where('type_id', $filters['type_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }

    /**
     * Get a single batch with details.
     *
     * @param int $id
     * @param User $user
     * @return DocumentBatch
     * @throws UnauthorizedAccessException
     */
    public function getBatch(int $id, User $user): DocumentBatch
    {
        $batch = DocumentBatch::with([
            'documentType',
            'uploader:id,name,last_name,email',
            'documents:id,batch_id,employee_document_number,status,user_id',
            'documents.user:id,name,last_name'
        ])->findOrFail($id);

        // Autorizado contra la empresa DEL LOTE, no con el rol global: antes
        // bastaba con que el rol global no fuera 'client', así que un usuario
        // que fuera admin en la empresa A podía abrir lotes de la empresa B.
        if (!$user->can('documents.view_batches', $batch->tenant_id)) {
            throw new UnauthorizedAccessException('No autorizado');
        }

        return $batch;
    }

    /**
     * Upload and create a new batch from ZIP file.
     *
     * @param UploadedFile $file
     * @param array $data
     * @param User $user
     * @param int $tenantId
     * @return DocumentBatch
     */
    public function uploadBatch(UploadedFile $file, array $data, User $user, int $tenantId): DocumentBatch
    {
        // Store ZIP temporarily
        $tempPath = $file->store('temp', 'local');

        // Create batch record
        $batch = DocumentBatch::create([
            'tenant_id' => $tenantId,
            'uploaded_by' => $user->id,
            'type_id' => $data['type_id'],
            'period' => $data['period'],
            'original_filename' => $file->getClientOriginalName(),
            'notify_employees' => $data['notify_employees'] ?? false,
            'requires_signature' => $data['requires_signature'] ?? false,
            // Ítem 36: tamaño de página elegido por el usuario antes de la
            // carga masiva, usado luego para calibrar la posición de la
            // firma (ver PdfWatermarkService / config/signature.php).
            'page_size' => $data['page_size'] ?? 'a10',
            'status' => 'pending',
        ]);

        // Dispatch job
        ProcessZipFile::dispatch($batch, $tempPath)->onQueue('documents');

        // El conteo real de documentos se conoce al completar el lote
        // (batch.completed, desde BatchCompletedCallback); aquí solo el evento
        // de creación con el nombre del archivo cargado.
        $this->auditService->logBatchCreated($batch->id, 0, $tenantId);

        return $batch;
    }

    /**
     * Preview ZIP file contents before processing.
     *
     * @param UploadedFile $file
     * @return array
     */
    public function previewZip(UploadedFile $file): array
    {
        $zip = new \ZipArchive();
        $tempPath = $file->getRealPath();

        if ($zip->open($tempPath) !== true) {
            return [
                'success' => false,
                'error' => 'No se pudo abrir el archivo ZIP',
            ];
        }

        $files = [];
        $validPdfs = 0;
        $invalidNames = [];
        $invalidFormats = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = basename($stat['name']);

            // Ignore directories and hidden files
            if (str_ends_with($stat['name'], '/') || str_starts_with($filename, '.')) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            // Verify it's a PDF
            if ($extension !== 'pdf') {
                $invalidFormats[] = [
                    'file' => $filename,
                    'reason' => 'Formato no permitido (solo PDF)',
                ];
                continue;
            }

            // Verify name is a valid document number (8-11 digits)
            if (!preg_match('/^\d{8,11}$/', $nameWithoutExt)) {
                $invalidNames[] = [
                    'file' => $filename,
                    'reason' => 'El nombre debe ser un número de documento válido (8-11 dígitos)',
                ];
                $files[] = [
                    'name' => $filename,
                    'size' => $stat['size'],
                    'valid' => false,
                    'reason' => 'Nombre inválido',
                ];
                continue;
            }

            $validPdfs++;
            $files[] = [
                'name' => $filename,
                'size' => $stat['size'],
                'valid' => true,
                'document_number' => $nameWithoutExt,
            ];
        }

        $zip->close();

        return [
            'success' => true,
            'total_files' => count($files) + count($invalidFormats),
            'valid_pdfs' => $validPdfs,
            'invalid_names' => $invalidNames,
            'invalid_formats' => $invalidFormats,
            'files' => array_slice($files, 0, 100), // Limit preview
        ];
    }

    /**
     * Get batch status summary.
     *
     * @param DocumentBatch $batch
     * @return array
     */
    public function getBatchStatus(DocumentBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'progress_percentage' => $batch->progress_percentage,
            'total_files' => $batch->total_files,
            'processed_files' => $batch->processed_files,
            'success_count' => $batch->success_count,
            'error_count' => $batch->error_count,
            'orphan_count' => $batch->orphan_count,
        ];
    }

    /**
     * Transform batch for list response.
     *
     * @param DocumentBatch $batch
     * @return array
     */
    public function transformBatchForList(DocumentBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'document_type' => $batch->documentType,
            'period' => $batch->period,
            'original_filename' => $batch->original_filename,
            'uploader' => $batch->uploader ? [
                'id' => $batch->uploader->id,
                'name' => $batch->uploader->name . ' ' . $batch->uploader->last_name,
            ] : null,
            'total_files' => $batch->total_files,
            'processed_files' => $batch->processed_files,
            'success_count' => $batch->success_count,
            'replaced_count' => $batch->replaced_count,
            'orphan_count' => $batch->orphan_count,
            'error_count' => $batch->error_count,
            'status' => $batch->status,
            'progress_percentage' => $batch->progress_percentage,
            'notify_employees' => $batch->notify_employees,
            'requires_signature' => $batch->requires_signature,
            'page_size' => $batch->resolved_page_size,
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'created_at' => $batch->created_at,
        ];
    }

    /**
     * Transform batch for detail response.
     *
     * @param DocumentBatch $batch
     * @return array
     */
    public function transformBatchForDetail(DocumentBatch $batch): array
    {
        $data = $this->transformBatchForList($batch);

        $data['uploader'] = $batch->uploader;
        $data['errors'] = $batch->errors;
        $data['notifications_sent'] = $batch->notifications_sent;
        $data['documents_summary'] = [
            'total' => $batch->documents->count(),
            'pending' => $batch->documents->where('status', 'pending')->count(),
            'signed' => $batch->documents->where('status', 'signed')->count(),
            'orphan' => $batch->documents->where('status', 'orphan')->count(),
        ];

        return $data;
    }

    /**
     * ¿El usuario puede ver el módulo de lotes de carga en la empresa indicada?
     *
     * Antes era `getCurrentRole() !== 'client'`: sin empresa (rol global) y por
     * descarte, lo que concedía acceso a cualquiera que fuera algo distinto de
     * client en CUALQUIER empresa suya. Ahora se evalúa la ability dentro de la
     * empresa activa.
     *
     * Nota: según la Matriz de Accesos, 'documents.view_batches' es
     * [admin, admin_tenant] — root NO ve lotes (el router del frontend ya lo
     * excluía de /batches).
     *
     * @param int|null $tenantId Empresa activa (ver ActiveTenantResolver).
     */
    public function canAccessBatches(User $user, ?int $tenantId = null): bool
    {
        return $user->can('documents.view_batches', $tenantId);
    }
}
