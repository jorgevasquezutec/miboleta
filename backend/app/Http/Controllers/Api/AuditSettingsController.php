<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAuditSettingsRequest;
use App\Services\AuditSettingsService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Configuración de Auditoría",
 *     description="Mantenedor solo-root para activar/desactivar la captura de cada tipo de audit log en runtime."
 * )
 *
 * Las acciones críticas (AuditLog::ALWAYS_ON) no son desactivables: el service
 * las filtra y AuditService las fuerza a capturarse siempre.
 */
class AuditSettingsController extends Controller
{
    public function __construct(
        protected AuditSettingsService $auditSettingsService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/audit/settings",
     *     tags={"Configuración de Auditoría"},
     *     summary="Catálogo de acciones de auditoría con su estado y volumen",
     *     description="Devuelve todas las acciones auditables con etiqueta, categoría, si está bloqueada (always-on), si está activa y cuántos registros existen. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Catálogo de acciones"),
     *     @OA\Response(response=403, description="No autorizado - Solo root")
     * )
     */
    public function show(Request $request)
    {
        try {
            $catalog = $this->auditSettingsService->getCatalog($request->user());

            return response()->json(['data' => $catalog]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/audit/settings",
     *     tags={"Configuración de Auditoría"},
     *     summary="Actualizar qué acciones de auditoría se capturan",
     *     description="Recibe el conjunto de acciones cuya captura se DESACTIVA. Las always-on se ignoran. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="disabled_actions", type="array", @OA\Items(type="string"), example={"document.viewed","document.downloaded"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Configuración actualizada"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function update(UpdateAuditSettingsRequest $request)
    {
        $validated = $request->validated();

        try {
            $this->auditSettingsService->updateSettings(
                $request->user(),
                $validated['disabled_actions']
            );

            // Devolver el catálogo actualizado para refrescar la UI de una vez.
            $catalog = $this->auditSettingsService->getCatalog($request->user());

            return response()->json([
                'message' => 'Configuración de auditoría actualizada exitosamente',
                'data' => $catalog,
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }
}
