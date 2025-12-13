<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Tipos de Documento",
 *     description="Catálogo de tipos de documentos disponibles"
 * )
 */
class DocumentTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/document-types",
     *     tags={"Tipos de Documento"},
     *     summary="Listar tipos de documento",
     *     description="Lista todos los tipos de documento activos (boleta, liquidación, CTS, etc.)",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de tipos de documento",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="boleta"),
     *                 @OA\Property(property="display_name", type="string", example="Boleta de Pago"),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="requires_signature", type="boolean", example=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(): JsonResponse
    {
        $types = DocumentType::active()
            ->ordered()
            ->get(['id', 'name', 'display_name', 'description', 'requires_signature']);

        return response()->json([
            'data' => $types
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/document-types/{id}",
     *     tags={"Tipos de Documento"},
     *     summary="Obtener tipo de documento",
     *     description="Obtiene los detalles de un tipo de documento específico",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del tipo de documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Detalles del tipo de documento",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/DocumentType")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tipo no encontrado")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $type = DocumentType::findOrFail($id);

        return response()->json([
            'data' => new DocumentTypeResource($type)
        ]);
    }
}
