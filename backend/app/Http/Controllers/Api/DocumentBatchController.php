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
     * Lista el historial de cargas (batches)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->batchService->canAccessBatches($user)) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $filters = [
            'tenant_id' => $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id,
            'status' => $request->status,
            'type_id' => $request->type_id,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'per_page' => $request->get('per_page', 15),
        ];

        $batches = $this->batchService->getBatches($user, $filters);

        return response()->json([
            'data' => $batches->map(fn($batch) => $this->batchService->transformBatchForList($batch)),
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
     * Vista previa del contenido de un ZIP antes de procesar
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
