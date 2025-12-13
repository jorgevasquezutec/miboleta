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
     * @OA\Get(
     *     path="/api/documents",
     *     tags={"Documentos"},
     *     summary="Listar documentos",
     *     description="Lista documentos según rol: Root/Admin ven todos del tenant, Client solo los suyos",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="X-Tenant-Id",
     *         in="header",
     *         description="ID del tenant",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="my_documents",
     *         in="query",
     *         description="Si true, retorna solo documentos del usuario autenticado",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrar por estado",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "signed", "orphan", "active"})
     *     ),
     *     @OA\Parameter(
     *         name="doc_type_id",
     *         in="query",
     *         description="Filtrar por tipo de documento",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Filtrar por periodo (ej: 2024-01)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Búsqueda por nombre de usuario o documento",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="Lista de documentos",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Document")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
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
     * @OA\Get(
     *     path="/api/documents/{id}",
     *     tags={"Documentos"},
     *     summary="Obtener documento",
     *     description="Obtiene los detalles de un documento específico",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del documento",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Document")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
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
     * @OA\Get(
     *     path="/api/documents/{id}/download",
     *     tags={"Documentos"},
     *     summary="Descargar documento",
     *     description="Descarga el archivo PDF del documento",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Archivo PDF",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
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
     * @OA\Get(
     *     path="/api/documents/{id}/preview",
     *     tags={"Documentos"},
     *     summary="Preview de documento",
     *     description="Muestra el PDF inline en el navegador (sin descargar)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Archivo PDF inline",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
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
     * @OA\Get(
     *     path="/api/documents/orphans",
     *     tags={"Documentos"},
     *     summary="Listar documentos huérfanos",
     *     description="Lista documentos sin usuario asignado (solo admin/root)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="X-Tenant-Id",
     *         in="header",
     *         description="ID del tenant",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *         description="Lista de documentos huérfanos",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Document")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado (solo admin/root)")
     * )
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
     * @OA\Post(
     *     path="/api/documents/{id}/assign",
     *     tags={"Documentos"},
     *     summary="Asignar documento huérfano",
     *     description="Asigna un documento huérfano a un usuario",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id"},
     *             @OA\Property(property="user_id", type="string", description="ID del usuario")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento asignado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Documento asignado correctamente"),
     *             @OA\Property(property="data", ref="#/components/schemas/Document")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Documento no es huérfano"),
     *     @OA\Response(response=404, description="Documento o usuario no encontrado")
     * )
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
     * @OA\Delete(
     *     path="/api/documents/{id}",
     *     tags={"Documentos"},
     *     summary="Eliminar documento",
     *     description="Elimina un documento (soft delete)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento eliminado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Documento eliminado correctamente")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
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
