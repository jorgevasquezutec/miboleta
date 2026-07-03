<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureCertificateRequest;
use App\Http\Requests\UpdateSignatureSettingsRequest;
use App\Models\SignatureSettings;
use App\Services\SignatureCertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * @OA\Tag(
 *     name="Configuración de Firma Digital",
 *     description="Configuración del certificado de firma digital (.pfx/.p12) de la plataforma. Solo accesible para root."
 * )
 *
 * Endpoints SOLO ROOT para configurar el certificado de firma digital ÚNICO
 * de la plataforma (DS-009-2011-TR). Este controller es AGNÓSTICO al
 * firmador: no dispara ninguna firma, solo permite cargar/activar/eliminar
 * la configuración de forma segura. Nunca expone la password ni el binario
 * del certificado.
 */
class SignatureSettingsController extends Controller
{
    public function __construct(
        protected SignatureCertificateService $signatureCertificateService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/signature/settings",
     *     tags={"Configuración de Firma Digital"},
     *     summary="Obtener configuración de firma digital",
     *     description="Devuelve el estado de la firma digital de plataforma, sin exponer la password ni el binario del certificado. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Configuración de firma digital",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="signature_enabled", type="boolean"),
     *                 @OA\Property(property="has_certificate", type="boolean"),
     *                 @OA\Property(property="certificate_subject", type="string", nullable=true),
     *                 @OA\Property(property="tsa_url", type="string", nullable=true),
     *                 @OA\Property(property="uploaded_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado - Solo root")
     * )
     */
    public function show(Request $request)
    {
        try {
            $settings = $this->signatureCertificateService->getSettings($request->user());

            return response()->json([
                'data' => $this->transform($settings),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/signature/certificate",
     *     tags={"Configuración de Firma Digital"},
     *     summary="Cargar certificado de firma digital",
     *     description="Sube el certificado (.pfx/.p12) único de la plataforma. Reemplaza el certificado anterior si existía. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"certificate", "password"},
     *                 @OA\Property(property="certificate", type="string", format="binary", description="Archivo .pfx o .p12, máx. 10MB"),
     *                 @OA\Property(property="password", type="string", description="Contraseña del certificado"),
     *                 @OA\Property(property="tsa_url", type="string", nullable=true, description="URL del servicio de sello de tiempo (TSA)")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Certificado cargado exitosamente"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="Archivo o contraseña inválidos")
     * )
     */
    public function store(StoreSignatureCertificateRequest $request)
    {
        $validated = $request->validated();

        try {
            $settings = $this->signatureCertificateService->storeCertificate(
                $request->user(),
                $request->file('certificate'),
                $validated['password'],
                $validated['tsa_url'] ?? null
            );

            return response()->json([
                'message' => 'Certificado de firma cargado exitosamente',
                'data' => $this->transform($settings),
            ], 201);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('[SignatureSettingsController] Error al cargar certificado de firma', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al procesar el certificado de firma',
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/signature/settings",
     *     tags={"Configuración de Firma Digital"},
     *     summary="Activar/desactivar firma digital",
     *     description="Activa o desactiva el uso de la firma digital con el certificado de plataforma. No se puede activar sin certificado cargado. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"signature_enabled"},
     *             @OA\Property(property="signature_enabled", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Configuración actualizada exitosamente"),
     *     @OA\Response(response=403, description="No autorizado - Solo root"),
     *     @OA\Response(response=422, description="No se puede activar sin certificado cargado")
     * )
     */
    public function update(UpdateSignatureSettingsRequest $request)
    {
        $validated = $request->validated();

        try {
            $settings = $this->signatureCertificateService->enableSignature(
                $request->user(),
                (bool) $validated['signature_enabled']
            );

            return response()->json([
                'message' => $settings->signature_enabled
                    ? 'Firma digital activada exitosamente'
                    : 'Firma digital desactivada exitosamente',
                'data' => $this->transform($settings),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/signature/certificate",
     *     tags={"Configuración de Firma Digital"},
     *     summary="Eliminar certificado de firma digital",
     *     description="Elimina el certificado vigente (binario + configuración) y desactiva la firma digital. Solo root.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Certificado eliminado exitosamente"),
     *     @OA\Response(response=403, description="No autorizado - Solo root")
     * )
     */
    public function destroy(Request $request)
    {
        try {
            $settings = $this->signatureCertificateService->deleteCertificate($request->user());

            return response()->json([
                'message' => 'Certificado de firma eliminado exitosamente',
                'data' => $this->transform($settings),
            ]);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    /**
     * Transforma la configuración de firma para la API, ocultando
     * SIEMPRE certificate_path y certificate_password.
     */
    private function transform(SignatureSettings $settings): array
    {
        return [
            'signature_enabled' => (bool) $settings->signature_enabled,
            'has_certificate' => $settings->hasCertificate(),
            'certificate_subject' => $settings->certificate_subject,
            'tsa_url' => $settings->tsa_url,
            'uploaded_at' => $settings->uploaded_at,
        ];
    }
}
