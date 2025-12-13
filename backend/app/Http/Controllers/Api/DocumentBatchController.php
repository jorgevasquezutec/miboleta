<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReprocessChunkRequest;
use App\Http\Requests\UploadZipBatchRequest;
use App\Jobs\ProcessZipFile;
use App\Models\DocumentBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentBatchController extends Controller
{
    /**
     * Lista el historial de cargas (batches)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;

        $query = DocumentBatch::with(['documentType:id,name,display_name', 'uploader:id,name,last_name'])
            ->orderBy('created_at', 'desc');

        if ($tenantId && $role !== 'root') {
            $query->where('tenant_id', $tenantId);
        }

        // Filtros opcionales
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type_id')) {
            $query->where('type_id', $request->type_id);
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 15);
        $batches = $query->paginate($perPage);

        return response()->json([
            'data' => $batches->map(function ($batch) {
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
                    'started_at' => $batch->started_at,
                    'completed_at' => $batch->completed_at,
                    'created_at' => $batch->created_at,
                ];
            }),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ]
        ]);
    }

    /**
     * Muestra detalles de un batch específico incluyendo errores
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $batch = DocumentBatch::with([
            'documentType',
            'uploader:id,name,last_name,email',
            'documents:id,batch_id,employee_document_number,status,user_id',
            'documents.user:id,name,last_name'
        ])->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'document_type' => $batch->documentType,
                'period' => $batch->period,
                'original_filename' => $batch->original_filename,
                'uploader' => $batch->uploader,
                'total_files' => $batch->total_files,
                'processed_files' => $batch->processed_files,
                'success_count' => $batch->success_count,
                'replaced_count' => $batch->replaced_count,
                'orphan_count' => $batch->orphan_count,
                'error_count' => $batch->error_count,
                'errors' => $batch->errors,
                'status' => $batch->status,
                'progress_percentage' => $batch->progress_percentage,
                'notify_employees' => $batch->notify_employees,
                'notifications_sent' => $batch->notifications_sent,
                'requires_signature' => $batch->requires_signature,
                'started_at' => $batch->started_at,
                'completed_at' => $batch->completed_at,
                'created_at' => $batch->created_at,
                'documents_summary' => [
                    'total' => $batch->documents->count(),
                    'pending' => $batch->documents->where('status', 'pending')->count(),
                    'signed' => $batch->documents->where('status', 'signed')->count(),
                    'orphan' => $batch->documents->where('status', 'orphan')->count(),
                ],
            ]
        ]);
    }

    /**
     * Sube un archivo ZIP para procesamiento
     * (El procesamiento real se hará en un Job separado)
     */
    public function upload(UploadZipBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        $tenantId = $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id;

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant no especificado'], 400);
        }

        // Guardar el archivo ZIP temporalmente
        $file = $request->file('file');
        $tempPath = $file->store('temp', 'local');

        // Crear el batch
        $batch = DocumentBatch::create([
            'tenant_id' => $tenantId,
            'uploaded_by' => $user->id,
            'type_id' => $validated['type_id'],
            'period' => $validated['period'],
            'original_filename' => $file->getClientOriginalName(),
            'notify_employees' => $validated['notify_employees'] ?? false,
            'requires_signature' => $validated['requires_signature'] ?? false,
            'status' => 'pending',
        ]);

        // Disparar Job para procesar el ZIP
        ProcessZipFile::dispatch($batch, $tempPath)->onQueue('documents');

        return response()->json([
            'message' => 'Archivo recibido. El procesamiento comenzará en breve.',
            'data' => [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]
        ], 202);
    }

    /**
     * Vista previa del contenido de un ZIP antes de procesar
     */
    public function previewZip(ReprocessChunkRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $file = $request->file('file');
        $zip = new \ZipArchive();
        $tempPath = $file->getRealPath();

        if ($zip->open($tempPath) !== true) {
            return response()->json(['error' => 'No se pudo abrir el archivo ZIP'], 400);
        }

        $files = [];
        $validPdfs = 0;
        $invalidNames = [];
        $invalidFormats = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $filename = basename($stat['name']);

            // Ignorar directorios y archivos ocultos
            if (str_ends_with($stat['name'], '/') || str_starts_with($filename, '.')) {
                continue;
            }

            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);

            // Verificar que sea PDF
            if ($extension !== 'pdf') {
                $invalidFormats[] = [
                    'file' => $filename,
                    'reason' => 'Formato no permitido (solo PDF)',
                ];
                continue;
            }

            // Verificar que el nombre sea un DNI válido (solo números, 8-11 dígitos)
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

        return response()->json([
            'data' => [
                'total_files' => count($files) + count($invalidFormats),
                'valid_pdfs' => $validPdfs,
                'invalid_names' => $invalidNames,
                'invalid_formats' => $invalidFormats,
                'files' => array_slice($files, 0, 100), // Limitar a 100 para la vista previa
            ]
        ]);
    }
}
