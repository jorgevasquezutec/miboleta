<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Roles",
 *     description="Catálogo de roles del sistema (globales y operativos por empresa)"
 * )
 */
class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Listar roles",
     *     description="Catálogo completo de roles. El frontend filtra 'root' al construir el selector de roles por empresa (tenants_config.*.role_ids), ya que root es global y no se asigna dentro de una empresa.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Lista de roles",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="admin"),
     *                 @OA\Property(property="display_name", type="string", example="Administrador de Tenant")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function index(): JsonResponse
    {
        $roles = Role::orderBy('id')->get(['id', 'name', 'display_name']);

        return response()->json([
            'data' => $roles,
        ]);
    }
}
