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
 * Endpoints SOLO ROOT para: (a) registrar la IP pública del servicio (ítem 23
 * del sprint-fix), valor informativo para whitelisting con terceros; y (b)
 * administrar el servidor SMTP global de la plataforma (mailer por defecto
 * usado cuando una empresa no tiene SMTP propio; ver TenantMailerService).
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
     *                 @OA\Property(property="mail_host", type="string", nullable=true),
     *                 @OA\Property(property="mail_port", type="integer", nullable=true),
     *                 @OA\Property(property="mail_username", type="string", nullable=true),
     *                 @OA\Property(property="mail_encryption", type="string", nullable=true, enum={"tls","ssl"}),
     *                 @OA\Property(property="mail_from_address", type="string", nullable=true),
     *                 @OA\Property(property="mail_from_name", type="string", nullable=true),
     *                 @OA\Property(property="has_mail_password", type="boolean"),
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
     *     summary="Actualizar configuración de la plataforma",
     *     description="Registra/actualiza la IP pública y el servidor SMTP global de la plataforma. Solo root. La contraseña SMTP es write-only: si se omite, se conserva la existente.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="public_ip", type="string", nullable=true, example="200.10.20.30"),
     *             @OA\Property(property="mail_host", type="string", nullable=true, example="smtp.office365.com"),
     *             @OA\Property(property="mail_port", type="integer", nullable=true, example=587),
     *             @OA\Property(property="mail_username", type="string", nullable=true),
     *             @OA\Property(property="mail_password", type="string", nullable=true, description="Write-only. Omitir para conservar la actual."),
     *             @OA\Property(property="mail_encryption", type="string", nullable=true, enum={"tls","ssl"}),
     *             @OA\Property(property="mail_from_address", type="string", nullable=true),
     *             @OA\Property(property="mail_from_name", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Configuración actualizada exitosamente"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function update(UpdatePlatformSettingsRequest $request)
    {
        $validated = $request->validated();

        try {
            $settings = $this->platformSettingsService->updateSettings(
                $request->user(),
                $validated
            );

            return response()->json([
                'message' => 'Configuración de plataforma actualizada exitosamente',
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
            // SMTP global (nunca la contraseña; solo un booleano de presencia).
            'mail_host' => $settings->mail_host,
            'mail_port' => $settings->mail_port,
            'mail_username' => $settings->mail_username,
            'mail_encryption' => $settings->mail_encryption,
            'mail_from_address' => $settings->mail_from_address,
            'mail_from_name' => $settings->mail_from_name,
            'has_mail_password' => !empty($settings->mail_password),
            'updated_at' => $settings->updated_at,
        ];
    }
}
