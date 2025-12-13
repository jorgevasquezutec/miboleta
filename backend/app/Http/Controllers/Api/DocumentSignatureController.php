<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifySignatureCodeRequest;
use App\Mail\SignatureCodeMail;
use App\Models\Document;
use App\Models\DocumentSignatureCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

/**
 * @OA\Tag(
 *     name="Firma de Documentos",
 *     description="Gestión de firma digital de documentos"
 * )
 */
class DocumentSignatureController extends Controller
{
    /**
     * Verificar si el usuario ya aceptó los términos de firma
     */
    public function checkTerms(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'accepted' => $user->signature_terms_accepted_at !== null,
            'accepted_at' => $user->signature_terms_accepted_at,
        ]);
    }

    /**
     * Aceptar términos y condiciones de firma digital
     */
    public function acceptTerms(Request $request): JsonResponse
    {
        $user = Auth::user();

        \Log::info('[DocumentSignatureController] acceptTerms called for user:', ['user_id' => $user->id]);

        if ($user->signature_terms_accepted_at) {
            \Log::info('[DocumentSignatureController] User already accepted terms at:', ['accepted_at' => $user->signature_terms_accepted_at]);
            return response()->json([
                'message' => 'Ya has aceptado los términos previamente',
                'accepted_at' => $user->signature_terms_accepted_at,
            ]);
        }

        $user->update([
            'signature_terms_accepted_at' => now(),
        ]);

        \Log::info('[DocumentSignatureController] Terms accepted successfully. Updated signature_terms_accepted_at to:', ['accepted_at' => $user->signature_terms_accepted_at]);

        return response()->json([
            'message' => 'Términos aceptados correctamente',
            'accepted_at' => $user->signature_terms_accepted_at,
        ]);
    }

    /**
     * Solicitar código de verificación para firmar un documento
     */
    public function requestCode(Request $request, int $documentId): JsonResponse
    {
        $user = Auth::user();

        \Log::info('[DocumentSignatureController] requestCode called', [
            'user_id' => $user->id,
            'document_id' => $documentId,
            'signature_terms_accepted_at' => $user->signature_terms_accepted_at,
        ]);

        // Verificar que haya aceptado términos
        if (!$user->signature_terms_accepted_at) {
            \Log::warning('[DocumentSignatureController] User has not accepted terms', ['user_id' => $user->id]);
            return response()->json([
                'error' => 'Debes aceptar los términos y condiciones antes de firmar',
                'requires_terms' => true,
            ], 400);
        }

        // Obtener documento
        $document = Document::with('documentType')->findOrFail($documentId);

        // Verificar que el documento pertenece al usuario
        if ($document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Verificar que el documento requiere firma
        if (!$document->requires_signature) {
            return response()->json(['error' => 'Este documento no requiere firma'], 400);
        }

        // Verificar que no esté ya firmado
        if ($document->isSigned()) {
            return response()->json(['error' => 'Este documento ya fue firmado'], 400);
        }

        // Verificar cooldown de código anterior
        $existingCode = DocumentSignatureCode::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existingCode && $existingCode->isInCooldown()) {
            return response()->json([
                'error' => 'Debes esperar antes de solicitar otro código',
                'cooldown_remaining' => $existingCode->cooldown_remaining,
            ], 429);
        }

        // Generar nuevo código
        $result = DocumentSignatureCode::createForDocument($document, $user);

        // Enviar email
        Mail::to($user->email)->send(new SignatureCodeMail(
            code: $result['code'],
            documentType: $document->documentType->display_name ?? 'Documento',
            period: $document->period,
            userName: $user->name
        ));

        return response()->json([
            'message' => 'Código enviado a tu correo electrónico',
            'expires_in' => DocumentSignatureCode::EXPIRY_MINUTES * 60,
            'email_sent_to' => $this->maskEmail($user->email),
        ]);
    }

    /**
     * Verificar código y firmar documento
     */
    public function verifyAndSign(VerifySignatureCodeRequest $request, int $documentId): JsonResponse
    {
        $validated = $request->validated();

        $user = Auth::user();

        // Obtener documento
        $document = Document::with('documentType')->findOrFail($documentId);

        // Verificar que el documento pertenece al usuario
        if ($document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Verificar que no esté ya firmado
        if ($document->isSigned()) {
            return response()->json(['error' => 'Este documento ya fue firmado'], 400);
        }

        // Obtener código activo
        $signatureCode = DocumentSignatureCode::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$signatureCode) {
            return response()->json([
                'error' => 'No hay código de verificación activo. Solicita uno nuevo.',
                'requires_new_code' => true,
            ], 400);
        }

        // Verificar expiración
        if ($signatureCode->isExpired()) {
            return response()->json([
                'error' => 'El código ha expirado. Solicita uno nuevo.',
                'requires_new_code' => true,
            ], 400);
        }

        // Verificar intentos máximos
        if ($signatureCode->hasMaxAttempts()) {
            return response()->json([
                'error' => 'Has excedido el número máximo de intentos. Solicita un nuevo código.',
                'requires_new_code' => true,
            ], 400);
        }

        // Verificar cooldown
        if ($signatureCode->isInCooldown()) {
            return response()->json([
                'error' => 'Debes esperar antes de intentar nuevamente',
                'cooldown_remaining' => $signatureCode->cooldown_remaining,
            ], 429);
        }

        // Verificar código
        if (!$signatureCode->verifyCode($validated['code'])) {
            $signatureCode->incrementAttempts();

            $remainingAttempts = DocumentSignatureCode::MAX_ATTEMPTS - $signatureCode->attempts;

            return response()->json([
                'error' => 'Código incorrecto',
                'remaining_attempts' => $remainingAttempts,
                'requires_new_code' => $remainingAttempts <= 0,
            ], 400);
        }

        // Código correcto - firmar documento
        $signatureCode->markAsUsed();

        $signatureData = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'verification_method' => 'email_2fa',
            'code_id' => $signatureCode->id,
        ];

        $document->sign($signatureData);

        return response()->json([
            'message' => 'Documento firmado correctamente',
            'signed_at' => $document->signed_at,
            'document' => [
                'id' => $document->id,
                'type' => $document->documentType->display_name,
                'period' => $document->period,
                'status' => $document->status,
            ],
        ]);
    }

    /**
     * Obtener estado de firma de un documento
     */
    public function status(int $documentId): JsonResponse
    {
        $user = Auth::user();
        $document = Document::with('documentType')->findOrFail($documentId);

        // Verificar acceso
        $role = $user->getCurrentRole();
        if ($role === 'client' && $document->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        return response()->json([
            'document_id' => $document->id,
            'requires_signature' => $document->requires_signature,
            'is_signed' => $document->isSigned(),
            'signed_at' => $document->signed_at,
            'signature' => $document->isSigned() ? [
                'timestamp' => $document->signature['timestamp'] ?? null,
                'verification_method' => $document->signature['verification_method'] ?? null,
            ] : null,
        ]);
    }

    /**
     * Mask email for privacy
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            return $email;
        }

        $masked = substr($name, 0, 2) . str_repeat('*', min(strlen($name) - 2, 5)) . '@' . $domain;
        return $masked;
    }
}
