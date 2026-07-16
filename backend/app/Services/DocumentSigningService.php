<?php

namespace App\Services;

use App\Exceptions\DocumentSigningException;
use App\Models\Document;
use App\Models\SignatureSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orquesta la firma digital CRIPTOGRÁFICA (PAdES, certificado ÚNICO de la
 * plataforma) de un Document: valida elegibilidad, delega el trabajo pesado
 * (Ghostscript + pyHanko) al sidecar HTTP `signer` (ver signer/app.py) y
 * reemplaza el archivo en el disco 'documents' SOLO si el sidecar confirmó
 * éxito, dejando metadata de la firma en Document::signature.
 *
 * IMPORTANTE: esto es un pipeline DISTINTO del flujo de "firma" por 2FA de
 * email (App\Services\SignatureService, donde el propio empleado confirma
 * que es él con un código enviado a su correo). Ese flujo sigue intacto: no
 * lo usa ni lo modifica este servicio. Ambos comparten el mismo campo
 * Document::signature (JSON) y el mismo status 'signed', distinguibles por
 * signature['method'] ('pades_pyhanko' aquí vs. lo que deje SignatureService,
 * que no define 'method' y sí 'verification_method' => 'email_2fa').
 */
class DocumentSigningService
{
    public function __construct(
        protected SignatureCertificateService $certificateService
    ) {
    }

    /**
     * Firma un documento con el certificado de plataforma y reemplaza el
     * archivo en disco. NO corrompe el original: solo lo reemplaza después
     * de confirmar que el sidecar devolvió éxito y que el archivo firmado
     * existe.
     *
     * @return array La metadata de firma (tal como quedó en Document::signature)
     * @throws DocumentSigningException Ante cualquier fallo de configuración,
     *                                   elegibilidad, o del propio sidecar.
     */
    public function signDocument(Document $document): array
    {
        $this->assertEligible($document);

        $settings = SignatureSettings::current();
        $certificatePath = $this->certificateService->getCertificateAbsolutePath();
        $certificatePassword = $this->certificateService->getDecryptedPassword();

        if (!$certificatePath || $certificatePassword === null) {
            throw new DocumentSigningException(
                'No hay un certificado de firma digital configurado en la plataforma.'
            );
        }

        if (!$document->fileExists()) {
            throw new DocumentSigningException(
                "El archivo del documento #{$document->id} no existe en disco.",
                404
            );
        }

        $inputPath = Storage::disk('documents')->path($document->file_path);
        $tempRelativePath = $this->buildTempPath($document->file_path);
        $tempAbsolutePath = Storage::disk('documents')->path($tempRelativePath);

        Storage::disk('documents')->makeDirectory(dirname($tempRelativePath));

        try {
            $response = Http::baseUrl(config('services.signer.base_url'))
                ->timeout((int) config('services.signer.timeout', 120))
                ->connectTimeout((int) config('services.signer.connect_timeout', 10))
                ->post('/sign', [
                    'input_path' => $inputPath,
                    'output_path' => $tempAbsolutePath,
                    'certificate_path' => $certificatePath,
                    'certificate_password' => $certificatePassword,
                    'tsa_url' => $settings->tsa_url,
                ]);
        } catch (ConnectionException $e) {
            $this->cleanupTemp($tempRelativePath);
            Log::error('[DocumentSigningService] No se pudo conectar al sidecar de firma', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            throw new DocumentSigningException(
                "No se pudo conectar al servicio de firma digital: {$e->getMessage()}",
                503
            );
        }

        $payload = $response->json() ?? [];

        if ($response->failed() || !($payload['success'] ?? false)) {
            $this->cleanupTemp($tempRelativePath);
            $error = $payload['error'] ?? $payload['message'] ?? "HTTP {$response->status()}";
            Log::error('[DocumentSigningService] El sidecar de firma reportó un fallo', [
                'document_id' => $document->id,
                'http_status' => $response->status(),
                'stage' => $payload['stage'] ?? null,
                'error' => $error,
            ]);
            throw new DocumentSigningException("No se pudo firmar el documento: {$error}");
        }

        if (!Storage::disk('documents')->exists($tempRelativePath)) {
            throw new DocumentSigningException(
                'El firmador reportó éxito pero no se encontró el archivo firmado.'
            );
        }

        $signature = $payload['signature'] ?? [];

        // Reemplazar el original SOLO ahora que el sidecar confirmó éxito Y
        // el archivo firmado existe. Si cualquier paso previo falló, el
        // original en $document->file_path NUNCA se tocó.
        $signedContent = Storage::disk('documents')->get($tempRelativePath);
        Storage::disk('documents')->put($document->file_path, $signedContent);
        $this->cleanupTemp($tempRelativePath);

        $document->update([
            'file_size' => Storage::disk('documents')->size($document->file_path),
        ]);

        $document->sign([
            'method' => 'pades_pyhanko',
            'signer_subject' => $signature['signer_subject'] ?? null,
            'signing_time' => $signature['signing_time'] ?? null,
            'tsa_applied' => $signature['tsa_applied'] ?? false,
            'tsa_time' => $signature['tsa_time'] ?? null,
            'digest_algo' => $signature['digest_algo'] ?? null,
            'sha256' => $signature['sha256_of_signed_file'] ?? null,
            'covers_whole_file' => $signature['covers_whole_file'] ?? null,
            'intact' => $signature['intact'] ?? null,
            'valid' => $signature['valid'] ?? null,
            'trusted' => $signature['trusted'] ?? null,
        ]);

        Log::info('[DocumentSigningService] Documento firmado exitosamente', [
            'document_id' => $document->id,
            'signer_subject' => $signature['signer_subject'] ?? null,
            'tsa_applied' => $signature['tsa_applied'] ?? false,
        ]);

        return $signature;
    }

    /**
     * Verifica la(s) firma(s) criptográfica(s) embebidas en un PDF ya firmado
     * (PAdES), delegando el trabajo al sidecar `signer` (POST /verify). NO
     * valida elegibilidad de negocio (eso lo hace el llamador según lo que
     * quiera reportar para documentos sin firma criptográfica): solo exige
     * que el archivo exista en disco.
     *
     * @return array Payload "verification" del sidecar: intact, valid,
     *                trusted, covers_whole_file, signer_subject,
     *                signing_time, tsa_applied, tsa_time, digest_algo.
     * @throws DocumentSigningException Ante fallo de conexión, error del
     *                                   sidecar, o archivo inexistente.
     */
    public function verifyDocument(Document $document): array
    {
        if (!$document->fileExists()) {
            throw new DocumentSigningException(
                "El archivo del documento #{$document->id} no existe en disco.",
                404
            );
        }

        $pdfPath = Storage::disk('documents')->path($document->file_path);

        try {
            $response = Http::baseUrl(config('services.signer.base_url'))
                ->timeout((int) config('services.signer.timeout', 120))
                ->connectTimeout((int) config('services.signer.connect_timeout', 10))
                ->post('/verify', [
                    'pdf_path' => $pdfPath,
                ]);
        } catch (ConnectionException $e) {
            Log::error('[DocumentSigningService] No se pudo conectar al sidecar de firma para verificar', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            throw new DocumentSigningException(
                "No se pudo conectar al servicio de firma digital: {$e->getMessage()}",
                503
            );
        }

        $payload = $response->json() ?? [];

        if ($response->failed() || !($payload['success'] ?? false)) {
            $error = $payload['error'] ?? $payload['message'] ?? "HTTP {$response->status()}";
            Log::warning('[DocumentSigningService] El sidecar de firma reportó un fallo al verificar', [
                'document_id' => $document->id,
                'http_status' => $response->status(),
                'stage' => $payload['stage'] ?? null,
                'error' => $error,
            ]);
            throw new DocumentSigningException("No se pudo verificar la firma del documento: {$error}");
        }

        return $payload['verification'] ?? [];
    }

    /**
     * Valida que el documento sea elegible para el pipeline de firma
     * criptográfica. Se usa tanto desde el Job automático (ProcessDocumentChunk
     * -> SignDocument) como desde el endpoint on-demand, para dar el mismo
     * mensaje de error en ambos casos.
     *
     * @throws DocumentSigningException
     */
    public function assertEligible(Document $document): void
    {
        if (!SignatureSettings::current()->signature_enabled) {
            throw new DocumentSigningException(
                'La firma digital de plataforma no está activada.'
            );
        }

        if (!$document->requires_signature) {
            throw new DocumentSigningException('Este documento no requiere firma digital.');
        }

        if ($document->isSigned()) {
            throw new DocumentSigningException('Este documento ya fue firmado.');
        }

        if ($document->isOrphan()) {
            throw new DocumentSigningException(
                'Este documento está huérfano (sin usuario asignado); asígnalo antes de firmarlo.'
            );
        }
    }

    /**
     * Ruta temporal (dentro del MISMO disco 'documents') donde el sidecar
     * escribe el PDF firmado antes de que se reemplace el original. Debe
     * vivir en el mismo disco/volumen que comparte con el contenedor
     * `signer` (bind mount ./backend:/var/www/html), no en un disco
     * distinto (p.ej. 'local'), porque el sidecar recibe rutas absolutas de
     * ese volumen compartido.
     */
    protected function buildTempPath(string $originalRelativePath): string
    {
        $dir = dirname($originalRelativePath);
        $name = pathinfo($originalRelativePath, PATHINFO_FILENAME);

        return sprintf('%s/.signing-tmp/%s-%s.pdf', $dir, $name, Str::random(20));
    }

    protected function cleanupTemp(string $tempRelativePath): void
    {
        try {
            if (Storage::disk('documents')->exists($tempRelativePath)) {
                Storage::disk('documents')->delete($tempRelativePath);
            }
        } catch (\Throwable $e) {
            Log::warning('[DocumentSigningService] No se pudo limpiar archivo temporal de firma', [
                'path' => $tempRelativePath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
