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
 *     description="Gestión de firma digital de documentos"
 * )
 */
class DocumentSignatureController extends Controller
{
    public function __construct(
        protected SignatureService $signatureService
    ) {
    }

    /**
     * Verificar si el usuario ya aceptó los términos de firma
     */
    public function checkTerms(): JsonResponse
    {
        return response()->json(
            $this->signatureService->checkTerms(Auth::user())
        );
    }

    /**
     * Aceptar términos y condiciones de firma digital
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
     * Solicitar código de verificación para firmar un documento
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
     * Verificar código y firmar documento
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
     * Obtener estado de firma de un documento
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
