<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifySignatureCodeRequest;
use App\Services\SignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="Firma de Documentos",
 *     description="Gestión de firma digital de documentos con 2FA"
 * )
 */
class DocumentSignatureController extends Controller
{
    public function __construct(
        protected SignatureService $signatureService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/signature/terms",
     *     tags={"Firma de Documentos"},
     *     summary="Verificar términos aceptados",
     *     description="Verifica si el usuario ya aceptó los términos y condiciones de firma digital",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Estado de aceptación de términos",
     *         @OA\JsonContent(
     *             @OA\Property(property="accepted", type="boolean", example=true),
     *             @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function checkTerms(): JsonResponse
    {
        return response()->json(
            $this->signatureService->checkTerms(Auth::user())
        );
    }

    /**
     * @OA\Post(
     *     path="/api/signature/terms/accept",
     *     tags={"Firma de Documentos"},
     *     summary="Aceptar términos de firma",
     *     description="Acepta los términos y condiciones de firma digital",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Términos aceptados",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Términos aceptados correctamente"),
     *             @OA\Property(property="accepted_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function acceptTerms(Request $request): JsonResponse
    {
        $result = $this->signatureService->acceptTerms(Auth::user());

        return response()->json([
            'message' => $result['message'],
            'accepted_at' => $result['accepted_at'],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/documents/{documentId}/signature/request-code",
     *     tags={"Firma de Documentos"},
     *     summary="Solicitar código de verificación",
     *     description="Envía un código de 6 dígitos al email del usuario para firmar el documento. Cooldown de 30 segundos.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="documentId",
     *         in="path",
     *         required=true,
     *         description="ID del documento a firmar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Código enviado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Código enviado a tu correo electrónico"),
     *             @OA\Property(property="expires_in", type="integer", example=300, description="Segundos hasta expiración"),
     *             @OA\Property(property="email_sent_to", type="string", example="ju****@email.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string"),
     *             @OA\Property(property="requires_terms", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado"),
     *     @OA\Response(
     *         response=429,
     *         description="Cooldown activo",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Debes esperar antes de solicitar otro código"),
     *             @OA\Property(property="cooldown_remaining", type="integer", example=25)
     *         )
     *     )
     * )
     */
    public function requestCode(Request $request, int $documentId): JsonResponse
    {
        try {
            $result = $this->signatureService->requestCode(Auth::user(), $documentId);

            if (!$result['success']) {
                $response = ['error' => $result['error']];

                if (isset($result['requires_terms'])) {
                    $response['requires_terms'] = $result['requires_terms'];
                }
                if (isset($result['cooldown_remaining'])) {
                    $response['cooldown_remaining'] = $result['cooldown_remaining'];
                }

                return response()->json($response, $result['status_code']);
            }

            return response()->json([
                'message' => $result['message'],
                'expires_in' => $result['expires_in'],
                'email_sent_to' => $result['email_sent_to'],
            ]);

        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/documents/{documentId}/signature/verify",
     *     tags={"Firma de Documentos"},
     *     summary="Verificar código y firmar",
     *     description="Verifica el código de 6 dígitos y firma el documento. Máximo 3 intentos.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="documentId",
     *         in="path",
     *         required=true,
     *         description="ID del documento a firmar",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", minLength=6, maxLength=6, example="123456", description="Código de 6 dígitos")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Documento firmado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Documento firmado correctamente"),
     *             @OA\Property(property="signed_at", type="string", format="date-time"),
     *             @OA\Property(property="document", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="period", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Código incorrecto o expirado",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Código incorrecto"),
     *             @OA\Property(property="remaining_attempts", type="integer", example=2),
     *             @OA\Property(property="requires_new_code", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
     */
    public function verifyAndSign(VerifySignatureCodeRequest $request, int $documentId): JsonResponse
    {
        $validated = $request->validated();

        try {
            $result = $this->signatureService->verifyAndSign(
                Auth::user(),
                $documentId,
                $validated['code'],
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            if (!$result['success']) {
                $response = ['error' => $result['error']];

                if (isset($result['remaining_attempts'])) {
                    $response['remaining_attempts'] = $result['remaining_attempts'];
                }
                if (isset($result['requires_new_code'])) {
                    $response['requires_new_code'] = $result['requires_new_code'];
                }
                if (isset($result['cooldown_remaining'])) {
                    $response['cooldown_remaining'] = $result['cooldown_remaining'];
                }

                return response()->json($response, $result['status_code']);
            }

            return response()->json([
                'message' => $result['message'],
                'signed_at' => $result['signed_at'],
                'document' => $result['document'],
            ]);

        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/documents/{documentId}/signature/status",
     *     tags={"Firma de Documentos"},
     *     summary="Estado de firma del documento",
     *     description="Obtiene el estado de firma de un documento",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="documentId",
     *         in="path",
     *         required=true,
     *         description="ID del documento",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Estado de firma",
     *         @OA\JsonContent(
     *             @OA\Property(property="document_id", type="integer"),
     *             @OA\Property(property="requires_signature", type="boolean"),
     *             @OA\Property(property="is_signed", type="boolean"),
     *             @OA\Property(property="signed_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="signature", type="object", nullable=true,
     *                 @OA\Property(property="timestamp", type="string"),
     *                 @OA\Property(property="verification_method", type="string", example="email_2fa")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Documento no encontrado")
     * )
     */
    public function status(int $documentId): JsonResponse
    {
        try {
            return response()->json(
                $this->signatureService->getStatus(Auth::user(), $documentId)
            );
        } catch (DocumentNotFoundException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        } catch (UnauthorizedAccessException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }
}
