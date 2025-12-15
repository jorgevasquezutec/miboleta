<?php

namespace App\Services;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Mail\SignatureCodeMail;
use App\Models\Document;
use App\Models\DocumentSignatureCode;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SignatureService
{
    public function __construct(
        protected AuditService $auditService
    ) {
    }

    /**
     * Check if user has accepted signature terms.
     *
     * @param User $user
     * @return array
     */
    public function checkTerms(User $user): array
    {
        return [
            'accepted' => $user->signature_terms_accepted_at !== null,
            'accepted_at' => $user->signature_terms_accepted_at,
        ];
    }

    /**
     * Accept signature terms for user.
     *
     * @param User $user
     * @return array
     */
    public function acceptTerms(User $user): array
    {
        Log::info('[SignatureService] acceptTerms called', ['user_id' => $user->id]);

        if ($user->signature_terms_accepted_at) {
            Log::info('[SignatureService] User already accepted terms', [
                'accepted_at' => $user->signature_terms_accepted_at
            ]);
            return [
                'already_accepted' => true,
                'message' => 'Ya has aceptado los términos previamente',
                'accepted_at' => $user->signature_terms_accepted_at,
            ];
        }

        $user->update(['signature_terms_accepted_at' => now()]);

        Log::info('[SignatureService] Terms accepted successfully', [
            'accepted_at' => $user->signature_terms_accepted_at
        ]);

        return [
            'already_accepted' => false,
            'message' => 'Términos aceptados correctamente',
            'accepted_at' => $user->signature_terms_accepted_at,
        ];
    }

    /**
     * Request signature verification code.
     *
     * @param User $user
     * @param int $documentId
     * @return array
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function requestCode(User $user, int $documentId): array
    {
        Log::info('[SignatureService] requestCode called', [
            'user_id' => $user->id,
            'document_id' => $documentId,
        ]);

        // Check terms
        if (!$user->signature_terms_accepted_at) {
            Log::warning('[SignatureService] User has not accepted terms', ['user_id' => $user->id]);
            return [
                'success' => false,
                'error' => 'Debes aceptar los términos y condiciones antes de firmar',
                'requires_terms' => true,
                'status_code' => 400,
            ];
        }

        // Get document
        $document = Document::with('documentType')->find($documentId);
        if (!$document) {
            throw new DocumentNotFoundException('Documento no encontrado');
        }

        // Verify ownership
        if ($document->user_id !== $user->id) {
            throw new UnauthorizedAccessException('No autorizado');
        }

        // Verify requires signature
        if (!$document->requires_signature) {
            return [
                'success' => false,
                'error' => 'Este documento no requiere firma',
                'status_code' => 400,
            ];
        }

        // Check if already signed
        if ($document->isSigned()) {
            return [
                'success' => false,
                'error' => 'Este documento ya fue firmado',
                'status_code' => 400,
            ];
        }

        // Check cooldown
        $existingCode = DocumentSignatureCode::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($existingCode && $existingCode->isInCooldown()) {
            return [
                'success' => false,
                'error' => 'Debes esperar antes de solicitar otro código',
                'cooldown_remaining' => $existingCode->cooldown_remaining,
                'status_code' => 429,
            ];
        }

        // Generate new code
        $result = DocumentSignatureCode::createForDocument($document, $user);

        // Send email
        $this->sendSignatureCodeEmail($user, $document, $result['code']);

        return [
            'success' => true,
            'message' => 'Código enviado a tu correo electrónico',
            'expires_in' => DocumentSignatureCode::EXPIRY_MINUTES * 60,
            'email_sent_to' => $this->maskEmail($user->email),
        ];
    }

    /**
     * Verify code and sign document.
     *
     * @param User $user
     * @param int $documentId
     * @param string $code
     * @param array $requestData IP, user agent, etc.
     * @return array
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function verifyAndSign(User $user, int $documentId, string $code, array $requestData): array
    {
        $document = Document::with('documentType')->find($documentId);
        if (!$document) {
            throw new DocumentNotFoundException('Documento no encontrado');
        }

        // Verify ownership
        if ($document->user_id !== $user->id) {
            throw new UnauthorizedAccessException('No autorizado');
        }

        // Check if already signed
        if ($document->isSigned()) {
            return [
                'success' => false,
                'error' => 'Este documento ya fue firmado',
                'status_code' => 400,
            ];
        }

        // Get active code
        $signatureCode = DocumentSignatureCode::where('document_id', $document->id)
            ->where('user_id', $user->id)
            ->where('used', false)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$signatureCode) {
            return [
                'success' => false,
                'error' => 'No hay código de verificación activo. Solicita uno nuevo.',
                'requires_new_code' => true,
                'status_code' => 400,
            ];
        }

        // Check expiration
        if ($signatureCode->isExpired()) {
            return [
                'success' => false,
                'error' => 'El código ha expirado. Solicita uno nuevo.',
                'requires_new_code' => true,
                'status_code' => 400,
            ];
        }

        // Check max attempts
        if ($signatureCode->hasMaxAttempts()) {
            return [
                'success' => false,
                'error' => 'Has excedido el número máximo de intentos. Solicita un nuevo código.',
                'requires_new_code' => true,
                'status_code' => 400,
            ];
        }

        // Check cooldown
        if ($signatureCode->isInCooldown()) {
            return [
                'success' => false,
                'error' => 'Debes esperar antes de intentar nuevamente',
                'cooldown_remaining' => $signatureCode->cooldown_remaining,
                'status_code' => 429,
            ];
        }

        // Verify code
        if (!$signatureCode->verifyCode($code)) {
            $signatureCode->incrementAttempts();
            $remainingAttempts = DocumentSignatureCode::MAX_ATTEMPTS - $signatureCode->attempts;

            return [
                'success' => false,
                'error' => 'Código incorrecto',
                'remaining_attempts' => $remainingAttempts,
                'requires_new_code' => $remainingAttempts <= 0,
                'status_code' => 400,
            ];
        }

        // Code correct - sign document
        $signatureCode->markAsUsed();

        $signatureData = [
            'ip' => $requestData['ip'] ?? null,
            'user_agent' => $requestData['user_agent'] ?? null,
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'verification_method' => 'email_2fa',
            'code_id' => $signatureCode->id,
        ];

        $document->sign($signatureData);

        // Audit log
        $this->auditService->logDocumentSigned($document->id, $user->id);

        return [
            'success' => true,
            'message' => 'Documento firmado correctamente',
            'signed_at' => $document->signed_at,
            'document' => [
                'id' => $document->id,
                'type' => $document->documentType->display_name,
                'period' => $document->period,
                'status' => $document->status,
            ],
        ];
    }

    /**
     * Get signature status for a document.
     *
     * @param User $user
     * @param int $documentId
     * @return array
     * @throws DocumentNotFoundException
     * @throws UnauthorizedAccessException
     */
    public function getStatus(User $user, int $documentId): array
    {
        $document = Document::with('documentType')->find($documentId);
        if (!$document) {
            throw new DocumentNotFoundException('Documento no encontrado');
        }

        // Check access
        $role = $user->getCurrentRole();
        if ($role === 'client' && $document->user_id !== $user->id) {
            throw new UnauthorizedAccessException('No autorizado');
        }

        return [
            'document_id' => $document->id,
            'requires_signature' => $document->requires_signature,
            'is_signed' => $document->isSigned(),
            'signed_at' => $document->signed_at,
            'signature' => $document->isSigned() ? [
                'timestamp' => $document->signature['timestamp'] ?? null,
                'verification_method' => $document->signature['verification_method'] ?? null,
            ] : null,
        ];
    }

    /**
     * Send signature code email.
     *
     * @param User $user
     * @param Document $document
     * @param string $code
     * @return void
     */
    protected function sendSignatureCodeEmail(User $user, Document $document, string $code): void
    {
        try {
            Mail::to($user->email)->send(new SignatureCodeMail(
                code: $code,
                documentType: $document->documentType->display_name ?? 'Documento',
                period: $document->period,
                userName: $user->name
            ));
        } catch (\Exception $e) {
            Log::warning('[SignatureService] Failed to send signature code email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mask email for privacy.
     *
     * @param string $email
     * @return string
     */
    public function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            return $email;
        }

        return substr($name, 0, 2) . str_repeat('*', min(strlen($name) - 2, 5)) . '@' . $domain;
    }
}
