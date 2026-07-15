<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\DocumentSigningException;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOrphanRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\SignDocument;
use App\Models\Document;
use App\Models\User;
use App\Services\ActiveTenantResolver;
use App\Services\DocumentService;
use App\Services\DocumentSigningService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        protected DocumentService $documentService,
        protected DocumentSigningService $documentSigningService,
        protected ActiveTenantResolver $activeTenantResolver
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
     *         name="X-Tenant-Ids",
     *         in="header",
     *         description="IDs de tenants separados por coma (ej: '1,2,3'). Si no se especifica, filtra por todos los tenants del usuario",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
     *     ),
     *     @OA\Parameter(
     *         name="my_documents",
     *         in="query",
     *         description="Si true, retorna solo documentos del usuario autenticado",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="tenant_id",
     *         in="query",
     *         description="Filtrar por organización (solo para usuarios root)",
     *         required=false,
     *         @OA\Schema(type="integer")
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
            'tenant_id' => $request->tenant_id, // Manual tenant filter for root users
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
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
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
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
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
                // Prevent browser caching to ensure signed documents show updated watermark
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (DocumentNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
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
     *         name="X-Tenant-Ids",
     *         in="header",
     *         description="IDs de tenants separados por coma (ej: '1,2,3')",
     *         required=false,
     *         @OA\Schema(type="string", example="1,2,3")
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

        // Ability evaluada dentro de la empresa activa (antes: rol global "no
        // client", que dejaba pasar a quien no fuera client en NINGUNA de sus
        // empresas, aunque en la activa sí lo fuera).
        $activeTenantId = $this->activeTenantResolver->resolve($request, $user);

        if (!$user->can('documents.view_orphans', $activeTenantId)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $filters = [
            // ✅ tenant_id removed - handled by TenantFilterScope
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
            return response()->json(['message' => $e->getMessage()], 400);
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
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/documents/{id}/sign-digital",
     *     tags={"Documentos"},
     *     summary="Firmar documento con el certificado digital de plataforma (on-demand)",
     *     description="Encola la firma digital CRIPTOGRÁFICA (PAdES, certificado de plataforma) de un documento puntual. Distinto del flujo de firma por 2FA de email (/documents/{id}/sign). Requiere que la firma de plataforma esté activada (Configuración > Firma Digital) y que el documento sea elegible (no huérfano, no firmado ya, requires_signature=true). Solo root/admin. Asíncrono: despacha el Job SignDocument (cola 'signing') y responde 202 de inmediato; el resultado final se refleja luego en Document.status/signature.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=202, description="Firma digital encolada correctamente"),
     *     @OA\Response(response=403, description="No autorizado (solo root/admin)"),
     *     @OA\Response(response=404, description="Documento no encontrado"),
     *     @OA\Response(response=422, description="Documento no elegible o firma digital no activada")
     * )
     */
    public function signDigital(int $id): JsonResponse
    {
        $user = Auth::user();

        try {
            $document = Document::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Documento no encontrado'], 404);
        }

        // Se autoriza contra la empresa DEL DOCUMENTO, no con el rol global:
        // antes, quien fuera admin en cualquiera de sus empresas podía lanzar la
        // firma digital de documentos de OTRA empresa. El documento se busca
        // primero justamente para poder scopear el rol.
        if (!$user->can('documents.sign_digital', $document->tenant_id)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        try {
            $this->documentSigningService->assertEligible($document);
        } catch (DocumentSigningException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        SignDocument::dispatch($document->id)->onQueue('signing');

        return response()->json([
            'message' => 'Firma digital encolada correctamente. El estado del documento se actualizará cuando el proceso termine.',
        ], 202);
    }

    /**
     * @OA\Get(
     *     path="/api/documents/{id}/verify-signature",
     *     tags={"Documentos"},
     *     summary="Verificar la firma digital criptográfica de un documento",
     *     description="Verifica (vía sidecar pyHanko) la firma PAdES embebida en el PDF: integridad, validez, confianza de la cadena de certificación y sello de tiempo (TSA). Autorizado igual que GET /documents/{id} (dueño del documento o root/admin/admin_tenant según reglas de DocumentService). Si el documento no tiene una firma criptográfica (p. ej. fue firmado con el flujo de 2FA de email, o aún no está firmado), devuelve verifiable=false con un motivo en vez de un error.",
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
     *         description="Resultado de la verificación",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="verifiable", type="boolean"),
     *                 @OA\Property(property="intact", type="boolean", nullable=true),
     *                 @OA\Property(property="valid", type="boolean", nullable=true),
     *                 @OA\Property(property="trusted", type="boolean", nullable=true),
     *                 @OA\Property(property="covers_whole_file", type="boolean", nullable=true),
     *                 @OA\Property(property="signer_subject", type="string", nullable=true),
     *                 @OA\Property(property="signing_time", type="string", nullable=true),
     *                 @OA\Property(property="tsa_applied", type="boolean", nullable=true),
     *                 @OA\Property(property="tsa_time", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado"),
     *     @OA\Response(response=422, description="Fallo al verificar (sidecar)"),
     *     @OA\Response(response=503, description="Servicio de firma no disponible")
     * )
     */
    public function verifyDigital(int $id): JsonResponse
    {
        try {
            $document = $this->documentService->getDocument($id, Auth::user());
        } catch (DocumentNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $signature = $document->signature ?? [];

        // Solo el pipeline criptográfico (PAdES/pyHanko) es verificable por
        // el sidecar. El flujo de 2FA por email (SignatureService) deja
        // signature['verification_method'] = 'email_2fa' y NO produce una
        // firma embebida en el PDF: no lo tratamos como error, sino como un
        // estado claro para la UI.
        if (($signature['method'] ?? null) !== 'pades_pyhanko') {
            return response()->json([
                'data' => [
                    'verifiable' => false,
                    'reason' => 'not_cryptographically_signed',
                    'message' => $document->isSigned()
                        ? 'Este documento fue firmado mediante el flujo de confirmación por código (2FA de email), que no genera una firma criptográfica verificable en el PDF.'
                        : 'Este documento aún no tiene una firma digital criptográfica aplicada.',
                ],
            ]);
        }

        try {
            $verification = $this->documentSigningService->verifyDocument($document);
        } catch (DocumentSigningException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 422);
        }

        return response()->json([
            'data' => array_merge(['verifiable' => true], $verification),
        ]);
    }
}
