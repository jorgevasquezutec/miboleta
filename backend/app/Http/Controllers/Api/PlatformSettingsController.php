<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePlatformSettingsRequest;
use App\Models\PlatformSettings;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Configuración de Plataforma",
 *     description="Configuración general de la plataforma (IP pública del servidor). Solo accesible para root."
 * )
 *
 * Endpoints SOLO ROOT para registrar la IP pública del servicio (ítem 23 del
 * sprint-fix). Valor informativo (whitelisting con terceros): no afecta el
 * comportamiento de red de la aplicación.
 */
class PlatformSettingsController extends Controller
{
    public function __construct(
        protected PlatformSettingsService $platformSettingsService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/platform/settings",
     *     tags={"Configuración de Plataforma"},
     *     summary="Obtener configuración de plataforma",
     *     description="Devuelve la IP pública registrada de la plataforma. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Configuración de plataforma",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="public_ip", type="string", nullable=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado - Solo root")
     * )
     */
    public function show(Request $request)
    {
        try {
            $settings = $this->platformSettingsService->getSettings($request->user());

            return response()->json([
                'data' => $this->transform($settings),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/platform/settings",
     *     tags={"Configuración de Plataforma"},
     *     summary="Actualizar IP pública de la plataforma",
     *     description="Registra/actualiza la IP pública del servidor donde corre la plataforma. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="public_ip", type="string", nullable=true, example="200.10.20.30")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Configuración actualizada exitosamente"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="IP inválida")
     * )
     */
    public function update(UpdatePlatformSettingsRequest $request)
    {
        $validated = $request->validated();

        try {
            $settings = $this->platformSettingsService->updateSettings(
                $request->user(),
                $validated['public_ip'] ?? null
            );

            return response()->json([
                'message' => 'IP pública actualizada exitosamente',
                'data' => $this->transform($settings),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    private function transform(PlatformSettings $settings): array
    {
        return [
            'public_ip' => $settings->public_ip,
            'updated_at' => $settings->updated_at,
        ];
    }
}
