<?php

namespace App\Services;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Mail\SignatureCodeMail;
use App\Models\Document;
use App\Models\DocumentSignatureCode;
use App\Models\User;
use App\Jobs\SignDocument;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SignatureService
{
    public function __construct(
        protected AuditService $auditService,
        protected PdfWatermarkService $pdfWatermarkService,
        protected TenantMailerService $tenantMailerService,
        protected DocumentSigningService $documentSigningService
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

        $this->auditService->logTermsAccepted($user->id);

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
        // Se eager-carga 'batch' porque necesitamos batch->page_size (ítem
        // 36) para elegir las coordenadas correctas del watermark; ver más
        // abajo, antes de pdfWatermarkService->addSignatureWatermark().
        $document = Document::with(['documentType', 'batch'])->find($documentId);
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
        $signatureData = [
            'ip' => $requestData['ip'] ?? null,
            'user_agent' => $requestData['user_agent'] ?? null,
            'timestamp' => now()->toISOString(),
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'verification_method' => 'email_2fa',
            'code_id' => $signatureCode->id,
        ];

        // La conformidad convive con la firma digital (PAdES) de la empresa:
        // se decide la rama bajo el lock por documento, con la fila releída,
        // para no pisar ni ser pisado por el job de firma (invariante: con
        // digital_signature_status = 'pending' el archivo es del pipeline PAdES).
        try {
            $document = $this->documentSigningService->withDocumentLock(
                $document->id,
                function () use ($document, $signatureData, $signatureCode) {
                    $signed = $this->registerConformity($document, $signatureData);
                    if ($signed !== null) {
                        $signatureCode->markAsUsed();
                    }

                    return $signed;
                }
            );
        } catch (LockTimeoutException $e) {
            // La conformidad NO quedó registrada y el código NO se consumió:
            // el trabajador puede reintentar con el mismo código.
            return [
                'success' => false,
                'error' => 'El documento se está procesando. Inténtalo de nuevo en unos segundos.',
                'status_code' => 409,
            ];
        }

        if ($document === null) {
            return [
                'success' => false,
                'error' => 'Este documento ya fue firmado',
                'status_code' => 400,
            ];
        }

        // Audit log
        $this->auditService->logDocumentSigned($document->id, $user->id);

        return [
            'success' => true,
            'message' => 'Documento firmado correctamente',
            'signed_at' => $document->signed_at,
            'digital_signature_status' => $document->digital_signature_status,
            'document' => [
                'id' => $document->id,
                'type' => $document->documentType->display_name,
                'period' => $document->period,
                'status' => $document->status,
            ],
        ];
    }

    /**
     * Registra la conformidad del trabajador. Se ejecuta bajo el lock del
     * documento. Devuelve el documento actualizado, o null si entretanto ya
     * había sido firmado.
     *
     *  - Si el documento pasa por el pipeline PAdES de la empresa: se guarda la
     *    conformidad y se encola SignDocument (cola 'signing-priority') para
     *    regenerar el PDF firmado con el nombre del trabajador. NO se usa FPDI
     *    (no puede abrir PDFs firmados y reescribirlos invalidaría la firma).
     *    La conformidad no depende de que el sidecar esté disponible.
     *  - Si no aplica: FPDI estampa el nombre sobre el archivo, como siempre.
     */
    protected function registerConformity(Document $document, array $signatureData): ?Document
    {
        $fresh = Document::with(['documentType', 'batch'])->find($document->id);

        if (!$fresh || $fresh->isSigned()) {
            return null;
        }

        // Lo que vio el trabajador al firmar (antes de cualquier estampado).
        $signatureData['document_sha256'] = $this->hashDocument($fresh);

        if ($this->documentSigningService->appliesTo($fresh)) {
            $signatureData['pdf_mark'] = 'pades';

            $fresh->sign($signatureData);
            $fresh->forceFill([
                'digital_signature_status' => 'pending',
                'digital_signature_error' => null,
            ])->save();

            SignDocument::dispatch($fresh->id)->onQueue('signing-priority');

            return $fresh;
        }

        // Sin PAdES: FPDI como siempre. pdf_mark se agrega ANTES de sign().
        $signatureData['pdf_mark'] = $fresh->file_path
            ? $this->documentSigningService->applyFpdiMark($fresh, $signatureData)
            : 'fpdi_failed';

        $fresh->sign($signatureData);

        return $fresh;
    }

    protected function hashDocument(Document $document): ?string
    {
        try {
            if (!$document->file_path || !$document->fileExists()) {
                return null;
            }

            return hash_file('sha256', Storage::disk('documents')->path($document->file_path)) ?: null;
        } catch (\Throwable $e) {
            return null;
        }
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

        // El rol se resuelve dentro de la empresa DEL DOCUMENTO, no con el
        // respaldo global: antes, ser admin en la empresa A permitía consultar
        // el estado de firma de documentos ajenos de la B (allí el usuario es
        // client, pero el rol global no resolvía 'client' y el check no frenaba).
        //
        // Además se invierte a lista blanca: el check anterior ("es client")
        // dejaba pasar a quien no tuviera NINGÚN rol en esa empresa (rol null),
        // o sea era fail-open.
        $isOwner = $document->user_id === $user->id;
        $role = User::roleForTenant($user, $document->tenant_id);

        if (!$isOwner && !in_array($role, ['root', 'admin', 'admin_tenant', 'aprobador'], true)) {
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
            // Firma digital (PAdES) de la empresa: es lo que consulta el
            // polling del visor mientras se regenera el PDF con la conformidad.
            'digital_signature_status' => $document->digital_signature_status,
            'digitally_signed_at' => $document->digitally_signed_at,
            'includes_conformity' => $document->digitalSignatureIncludesConformity(),
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
            // Enrutado por el mailer propio de la empresa del documento, con
            // fallback al de la plataforma; ver TenantMailerService.
            $this->tenantMailerService->send($document->tenant, $user->email, new SignatureCodeMail(
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
