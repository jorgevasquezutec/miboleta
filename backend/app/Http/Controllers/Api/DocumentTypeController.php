<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTypeController extends Controller
{
    /**
     * Lista todos los tipos de documento activos
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
     * Muestra un tipo de documento específico
     */
    public function show(int $id): JsonResponse
    {
        $type = DocumentType::findOrFail($id);

        return response()->json([
            'data' => $type
        ]);
    }
}
