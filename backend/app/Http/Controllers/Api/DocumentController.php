<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOrphanRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @OA\Tag(
 *     name="Documentos",
 *     description="Gestión de documentos (listado, descarga, asignación)"
 * )
 */
class DocumentController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {
    }

    /**
     * Lista los documentos según el rol del usuario
     * - Root/Admin: todos los documentos del tenant
     * - Client: solo sus documentos
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        $filters = [
            'my_documents' => $request->boolean('my_documents'),
            'tenant_id' => $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id,
            'status' => $request->status,
            'doc_type_id' => $request->doc_type_id,
            'period' => $request->period,
            'search' => $request->search,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'per_page' => $request->get('per_page', 15),
        ];

        $documents = $this->documentService->getDocuments($user, $filters);

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * Muestra un documento específico
     */
    public function show(int $id): JsonResponse
    {
        try {
            $document = $this->documentService->getDocument($id, Auth::user());
            return response()->json(['data' => new DocumentResource($document)]);
        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Descarga un documento
     */
    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $result = $this->documentService->downloadDocument($id, Auth::user());

            return response()->download($result['path'], $result['filename'], [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Preview un documento (inline, sin descargar)
     */
    public function preview(int $id): BinaryFileResponse|JsonResponse
    {
        try {
            $result = $this->documentService->previewDocument($id, Auth::user());

            return response()->file($result['path'], [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $result['filename'] . '"',
            ]);
        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * Lista documentos huérfanos (solo admin/root)
     */
    public function orphans(Request $request): JsonResponse
    {
        $user = Auth::user();
        $role = $user->getCurrentRole();

        if ($role === 'client') {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $filters = [
            'tenant_id' => $request->header('X-Tenant-Id') ?? $user->tenants->first()?->id,
            'per_page' => $request->get('per_page', 15),
        ];

        $documents = $this->documentService->getOrphanDocuments($user, $filters);

        return response()->json([
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'per_page' => $documents->perPage(),
                'total' => $documents->total(),
            ]
        ]);
    }

    /**
     * Asigna un documento huérfano a un usuario
     */
    public function assignOrphan(AssignOrphanRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();
        $document = Document::findOrFail($id);
        $targetUser = User::findOrFail($validated['user_id']);

        try {
            $assignedDocument = $this->documentService->assignOrphanDocument($document, $targetUser);

            return response()->json([
                'message' => 'Documento asignado correctamente',
                'data' => new DocumentResource($assignedDocument)
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Elimina un documento (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->documentService->deleteDocument($id, Auth::user());

            return response()->json([
                'message' => 'Documento eliminado correctamente'
            ]);
        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
