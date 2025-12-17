<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReprocessChunkRequest;
use App\Http\Requests\UploadZipBatchRequest;
use App\Services\DocumentBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Lotes de Documentos",
 *     description="Gestión de lotes de carga masiva de documentos"
 * )
 */
class DocumentBatchController extends Controller
{
    public function __construct(
        protected DocumentBatchService $batchService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/batches",
     *     tags={"Lotes de Documentos"},
     *     summary="Listar lotes de carga",
     *     description="Lista el historial de cargas masivas de documentos",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="X-Tenant-Ids",
     *         in="header",
     *         description="IDs de tenants (separados por coma)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrar por estado",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "failed"})
     *     ),
     *     @OA\Parameter(
     *         name="type_id",
     *         in="query",
     *         description="Filtrar por tipo de documento",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Fecha desde (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Fecha hasta (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Resultados por página",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de lotes",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/DocumentBatch")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado (solo admin/root)")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->batchService->canAccessBatches($user)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Obtener tenant IDs del middleware
        $tenantIds = $request->get('_tenant_filter_ids') ?? $user->tenants->pluck('id')->toArray();

        $filters = [
            'tenant_ids' => $tenantIds,
            'status' => $request->status,
            'type_id' => $request->type_id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'per_page' => $request->get('per_page', 15),
        ];

        $batches = $this->batchService->getBatches($user, $filters);

        return response()->json([
            'data' => collect($batches->items())->map(fn($batch) => $this->batchService->transformBatchForList($batch)),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/batches/{id}",
     *     tags={"Lotes de Documentos"},
     *     summary="Obtener detalle de lote",
     *     description="Obtiene los detalles de un lote incluyendo errores y estadísticas",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del lote",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del lote",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentBatch")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Lote no encontrado")
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $batch = $this->batchService->getBatch($id, Auth::user());

            return response()->json([
                'data' => $this->batchService->transformBatchForDetail($batch)
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/batches/upload",
     *     tags={"Lotes de Documentos"},
     *     summary="Subir lote de documentos",
     *     description="Sube un archivo ZIP con documentos PDF para procesamiento asíncrono. Los nombres de archivos deben ser el número de documento del empleado (ej: 12345678.pdf)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="X-Tenant-Ids",
     *         in="header",
     *         description="IDs de tenants (separados por coma)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file", "type_id", "period"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Archivo ZIP con PDFs"),
     *                 @OA\Property(property="type_id", type="integer", description="ID del tipo de documento"),
     *                 @OA\Property(property="period", type="string", description="Periodo (ej: 2024-01)"),
     *                 @OA\Property(property="notify_employees", type="boolean", default=false, description="Notificar a empleados por email"),
     *                 @OA\Property(property="requires_signature", type="boolean", default=false, description="Requiere firma digital")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Archivo recibido para procesamiento",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Archivo recibido. El procesamiento comenzará en breve."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="batch_id", type="integer"),
     *                 @OA\Property(property="status", type="string", example="pending")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Tenant no especificado"),
     *     @OA\Response(response=422, description="Error de validación")
     * )
     */
    public function upload(UploadZipBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        // El tenant_id viene en el request validado desde el frontend
        $tenantId = $validated['tenant_id'] ?? $user->tenants->first()?->id;

        if (!$tenantId) {
            return response()->json(['error' => 'Tenant no especificado'], 400);
        }

        $batch = $this->batchService->uploadBatch(
            $request->file('file'),
            $validated,
            $user,
            $tenantId
        );

        return response()->json([
            'message' => 'Archivo recibido. El procesamiento comenzará en breve.',
            'data' => [
                'batch_id' => $batch->id,
                'status' => $batch->status,
            ]
        ], 202);
    }

    /**
     * @OA\Post(
     *     path="/api/batches/preview",
     *     tags={"Lotes de Documentos"},
     *     summary="Vista previa de ZIP",
     *     description="Analiza el contenido de un archivo ZIP antes de procesar, mostrando archivos válidos e inválidos",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary", description="Archivo ZIP a analizar")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Análisis del ZIP",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="total_files", type="integer", example=50),
     *                 @OA\Property(property="valid_pdfs", type="integer", example=45),
     *                 @OA\Property(property="invalid_names", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="invalid_formats", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="files", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="No se pudo abrir el archivo ZIP")
     * )
     */
    public function previewZip(ReprocessChunkRequest $request): JsonResponse
    {
        $result = $this->batchService->previewZip($request->file('file'));

        if (!$result['success']) {
            return response()->json(['error' => $result['error']], 400);
        }

        unset($result['success']);

        return response()->json(['data' => $result]);
    }
}
