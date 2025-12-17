<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkUserUpload;
use App\Models\UserBatch;
use App\Services\BulkUserUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserBatchController extends Controller
{
    public function __construct(
        private BulkUserUploadService $service
    ) {
    }

    /**
     * GET /api/user-batches/config
     * Obtener configuración para el modal de template
     */
    public function getConfig()
    {
        $config = $this->service->getConfigData();

        return response()->json($config);
    }

    /**
     * POST /api/user-batches/template
     * Generar y descargar template Excel personalizado
     */
    public function downloadTemplate(Request $request)
    {
        $validated = $request->validate([
            'max_organizations' => 'required|integer|min:1|max:5',
            'organization_ids' => 'nullable|array',
            'organization_ids.*' => 'integer|exists:tenants,id',
        ]);

        return $this->service->generateTemplate(
            $validated['max_organizations'],
            $validated['organization_ids'] ?? null
        );
    }

    /**
     * GET /api/user-batches
     * Listar batches de carga masiva (historial)
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);

        $batches = UserBatch::with(['tenant', 'createdBy'])
            ->when($request->has('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return response()->json([
            'data' => $batches->map(fn($b) => [
                'id' => $b->id,
                'uuid' => $b->uuid,
                'filename' => $b->original_filename,
                'file_size' => $b->file_size,
                'tenant' => $b->tenant ? [
                    'id' => $b->tenant->id,
                    'name' => $b->tenant->name,
                ] : null,
                'created_by' => $b->createdBy ? [
                    'id' => $b->createdBy->id,
                    'name' => $b->createdBy->full_name,
                ] : null,
                'status' => $b->status,
                'status_text' => $b->status_text,
                'status_badge' => $b->status_badge,
                'total_rows' => $b->total_rows,
                'processed_rows' => $b->processed_rows,
                'created_users' => $b->created_users,
                'updated_users' => $b->updated_users,
                'failed_rows' => $b->failed_rows,
                'progress_percentage' => $b->progress_percentage,
                'formatted_progress' => $b->formatted_progress,
                'duration' => $b->duration,
                'has_errors' => $b->hasErrors(),
                'created_at' => $b->created_at,
                'started_at' => $b->started_at,
                'completed_at' => $b->completed_at,
            ]),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
                'from' => $batches->firstItem(),
                'to' => $batches->lastItem(),
            ],
        ]);
    }

    /**
     * GET /api/user-batches/{uuid}
     * Ver detalle de un batch específico
     */
    public function show(string $uuid)
    {
        $batch = UserBatch::with(['tenant', 'createdBy'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'id' => $batch->id,
            'uuid' => $batch->uuid,
            'filename' => $batch->original_filename,
            'file_size' => $batch->file_size,
            'file_path' => $batch->file_path,
            'tenant' => [
                'id' => $batch->tenant->id,
                'name' => $batch->tenant->name,
                'ruc' => $batch->tenant->ruc,
            ],
            'created_by' => [
                'id' => $batch->createdBy->id,
                'name' => $batch->createdBy->full_name,
                'email' => $batch->createdBy->email,
            ],
            'status' => $batch->status,
            'status_text' => $batch->status_text,
            'status_badge' => $batch->status_badge,
            'progress' => [
                'total_rows' => $batch->total_rows,
                'processed_rows' => $batch->processed_rows,
                'created_users' => $batch->created_users,
                'updated_users' => $batch->updated_users,
                'failed_rows' => $batch->failed_rows,
                'percentage' => $batch->progress_percentage,
                'formatted' => $batch->formatted_progress,
                'current_chunk' => $batch->current_chunk,
                'total_chunks' => $batch->total_chunks,
            ],
            'errors' => $batch->error_summary,
            'summary' => $batch->success_summary,
            'processing_options' => $batch->processing_options,
            'duration' => $batch->duration,
            'has_errors' => $batch->hasErrors(),
            'is_processing' => $batch->isProcessing(),
            'is_completed' => $batch->isCompleted(),
            'started_at' => $batch->started_at,
            'completed_at' => $batch->completed_at,
            'created_at' => $batch->created_at,
            'updated_at' => $batch->updated_at,
        ]);
    }

    /**
     * POST /api/user-batches/validate
     * Validar archivo y obtener preview de datos (sin procesar)
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
        ]);

        $file = $request->file('file');

        // Validar y parsear archivo
        $validation = $this->service->validateFile($file);

        // Retornar preview con datos parseados, errores y warnings
        return response()->json([
            'valid' => $validation['valid'],
            'data' => $validation['data'] ?? [], // Usuarios a crear
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
            'summary' => $validation['summary'],
            'file_info' => [
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }

    /**
     * POST /api/user-batches
     * Iniciar carga masiva de usuarios (datos ya validados)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:10240', // 10MB max
            'send_welcome_emails' => 'boolean',
            'update_existing' => 'boolean',
        ]);

        $file = $request->file('file');

        // 1. Validar y parsear archivo
        $validation = $this->service->validateFile($file);

        if (!$validation['valid']) {
            return response()->json([
                'message' => 'El archivo contiene errores de validación',
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'summary' => $validation['summary'],
            ], 422);
        }

        // Si hay warnings pero no errores, permitir continuar
        if ($validation['summary']['errors'] > 0) {
            return response()->json([
                'message' => 'El archivo contiene errores que deben corregirse',
                'errors' => $validation['errors'],
                'summary' => $validation['summary'],
            ], 422);
        }

        // 2. Guardar archivo en storage
        $path = $file->store('user-batches', 'private');

        // 3. Obtener tenant_id (null para usuarios root)
        $tenantId = auth()->user()->tenants->first()?->id;

        // 4. Crear batch en BD
        $batch = UserBatch::create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => auth()->id(),
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'status' => 'pending',
            'total_rows' => count($validation['data']),
            'processing_options' => [
                'send_welcome_emails' => $validated['send_welcome_emails'] ?? false,
                'update_existing' => $validated['update_existing'] ?? true,
            ],
        ]);

        // 4. Despachar Job asíncrono
        ProcessBulkUserUpload::dispatch($batch->uuid, $validation['data']);

        return response()->json([
            'message' => 'Carga masiva iniciada exitosamente',
            'batch' => [
                'uuid' => $batch->uuid,
                'total_rows' => $batch->total_rows,
                'status' => $batch->status,
                'status_text' => $batch->status_text,
            ],
            'warnings' => $validation['warnings'] ?? [],
        ], 201);
    }

    /**
     * GET /api/user-batches/{uuid}/errors
     * Descargar archivo Excel con errores del batch
     */
    public function downloadErrors(string $uuid)
    {
        $batch = UserBatch::where('uuid', $uuid)->firstOrFail();

        if (!$batch->hasErrors()) {
            return response()->json([
                'message' => 'Este batch no tiene errores para descargar',
            ], 404);
        }

        // TODO: Implementar export de errores
        // Por ahora retornamos los errores en JSON

        return response()->json([
            'message' => 'Error export not yet implemented',
            'errors' => $batch->error_summary,
        ], 501);
    }

    /**
     * DELETE /api/user-batches/{uuid}
     * Cancelar/eliminar un batch
     */
    public function destroy(string $uuid)
    {
        $batch = UserBatch::where('uuid', $uuid)->firstOrFail();

        // Solo permitir eliminar si NO está en procesamiento
        if ($batch->isProcessing()) {
            return response()->json([
                'message' => 'No se puede eliminar un batch que está en procesamiento',
            ], 409); // Conflict
        }

        // Eliminar archivo si existe
        if ($batch->file_path && Storage::disk('private')->exists($batch->file_path)) {
            Storage::disk('private')->delete($batch->file_path);
        }

        $batch->delete();

        return response()->json([
            'message' => 'Batch eliminado exitosamente',
        ]);
    }
}
