<?php

namespace App\Services;

use App\Exceptions\DocumentSigningException;
use App\Jobs\SignDocument;
use App\Models\Document;
use App\Models\Scopes\TenantFilterScope;
use App\Models\SignatureSettings;
use App\Services\Signature\CertificateUnavailableException;
use App\Services\Signature\EffectiveCertificate;
use App\Services\Signature\SignatureLayout;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Orquesta la firma digital CRIPTOGRÁFICA de la EMPRESA (PAdES, con el
 * certificado de la empresa del documento o, si no tiene, el global de la
 * plataforma) de un Document: valida elegibilidad, delega el trabajo pesado
 * (Ghostscript + pyHanko) al sidecar HTTP `signer` (ver signer/app.py) y
 * reemplaza el archivo en el disco 'documents' SOLO si el sidecar confirmó
 * éxito.
 *
 * Son DOS firmas distintas que conviven en el mismo documento:
 *   - Conformidad del trabajador (código 2FA por correo, SignatureService):
 *     columnas `status`, `signature` y `signed_at`. No es criptográfica.
 *   - Firma digital de la empresa (este servicio): columnas `digital_*`.
 *     Este servicio NUNCA toca `status`, `signature` ni `signed_at`.
 *
 * El PDF que se sirve siempre se genera como
 * PAdES(base normalizada + nombre del trabajador si ya firmó): nunca se
 * modifica un PDF ya firmado. La base (revisión 0 normalizada a PDF/A) se
 * guarda en `.originals/` (Document::originalStoragePath) y cada re-firma
 * parte de ella; el nombre del trabajador lo dibuja el sidecar en la misma
 * revisión incremental que la firma.
 *
 * Concurrencia: toda escritura de file_path / original_* /
 * digital_signature_status se hace bajo Cache::lock("document-file:{id}").
 * Invariante: con digital_signature_status = 'pending' el archivo pertenece
 * a este pipeline y FPDI (flujo 2FA sin certificado) no lo toca.
 */
class DocumentSigningService
{
    /** Prefijo del lock por documento (lo usan TODAS las escrituras del archivo). */
    public const LOCK_PREFIX = 'document-file:';

    /** Segundos máximos de espera del lock antes de rendirse (reintentable). */
    private const LOCK_WAIT = 10;

    public function __construct(
        protected SignatureCertificateService $certificateService,
        protected AuditService $auditService,
        protected PdfWatermarkService $pdfWatermarkService
    ) {
    }

    // ------------------------------------------------------------------
    // Reglas de negocio
    // ------------------------------------------------------------------

    /**
     * ¿Este documento pasa por el pipeline PAdES de la empresa?
     * (a) ya tiene firma digital, (b) ya está en cola/proceso, o
     * (c) la firma está activada con certificado vigente, el documento
     * requiere firma y no es huérfano. Nunca lanza: un certificado
     * inutilizable simplemente significa "no aplica".
     */
    public function appliesTo(Document $document): bool
    {
        if ($document->digital_signature !== null || $document->digital_signature_status === 'pending') {
            return true;
        }

        try {
            if (!SignatureSettings::current()->signature_enabled) {
                return false;
            }
            if (!$document->requires_signature || $document->isOrphan()) {
                return false;
            }

            $certificate = $this->certificateService->resolveForTenant($document->tenant_id);

            return $certificate !== null
                && !($certificate->expiresAt && $certificate->expiresAt->isPast());
        } catch (CertificateUnavailableException $e) {
            return false;
        } catch (\Throwable $e) {
            Log::warning('[DocumentSigningService] appliesTo: no se pudo resolver el certificado', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * ¿El archivo que se sirve difiere del estado deseado? Verdadero si nunca
     * se firmó, o si la conformidad del trabajador aún no está dibujada en el
     * PDF firmado (o ya está dibujada pero ya no corresponde).
     */
    public function needsSigning(Document $document): bool
    {
        if ($document->digital_signature === null) {
            return true;
        }

        return $document->digitalSignatureIncludesConformity()
            !== ($document->isSigned() && !$document->original_has_conformity);
    }

    /**
     * Valida que el documento sea elegible para el pipeline de firma
     * criptográfica. Se usa tanto desde el Job como desde el endpoint
     * on-demand, para dar el mismo mensaje de error en ambos casos.
     *
     * Con firma previa (re-firma para incluir la conformidad) no se exige
     * signature_enabled: solo un certificado vigente DEL MISMO firmante, para
     * que al borrar el certificado de la empresa no se caiga al global.
     *
     * @throws DocumentSigningException
     */
    public function assertEligible(Document $document): void
    {
        if (!$this->needsSigning($document)) {
            throw new DocumentSigningException('La firma digital de la empresa ya está al día.');
        }

        if ($document->digital_signature !== null) {
            $certificate = $this->effectiveCertificateOrFail($document);

            $previous = $document->digital_signature;
            if (($previous['certificate_source'] ?? null) !== $certificate->source
                || ($previous['certificate_ruc'] ?? null) !== $certificate->ruc) {
                throw new DocumentSigningException('El certificado actual no corresponde al firmante original.');
            }

            return;
        }

        if (!SignatureSettings::current()->signature_enabled) {
            throw new DocumentSigningException(
                'La firma digital de plataforma no está activada.'
            );
        }

        $this->effectiveCertificateOrFail($document);

        if (!$document->requires_signature) {
            throw new DocumentSigningException('Este documento no requiere firma digital.');
        }

        if ($document->isOrphan()) {
            throw new DocumentSigningException(
                'Este documento está huérfano (sin usuario asignado); asígnalo antes de firmarlo.'
            );
        }
    }

    // ------------------------------------------------------------------
    // Cola y lock
    // ------------------------------------------------------------------

    /**
     * Ejecuta $callback con el lock por documento. Lanza
     * LockTimeoutException si no se obtiene en LOCK_WAIT segundos.
     *
     * @throws LockTimeoutException
     */
    public function withDocumentLock(int $documentId, callable $callback)
    {
        return Cache::lock(self::LOCK_PREFIX . $documentId, 300)->block(self::LOCK_WAIT, $callback);
    }

    /**
     * Marca el documento como 'pending' (bajo el lock) y encola SignDocument.
     * Sirve para la primera firma, la re-firma y "Reintentar".
     *
     * @param string $queue 'signing' (lotes) | 'signing-priority' (conformidad / reintento)
     */
    public function markPendingAndDispatch(Document $document, string $queue = 'signing'): void
    {
        $this->withDocumentLock($document->id, function () use ($document) {
            $document->forceFill([
                'digital_signature_status' => 'pending',
                'digital_signature_error' => null,
            ])->save();
        });

        SignDocument::dispatch($document->id)->onQueue($queue);
    }

    /** Busca el documento sin el filtro de tenant de la sesión (contexto de job). */
    public function findFresh(int $documentId): ?Document
    {
        return Document::withoutGlobalScope(TenantFilterScope::class)->find($documentId);
    }

    // ------------------------------------------------------------------
    // Firma
    // ------------------------------------------------------------------

    /**
     * Firma (o re-firma) un documento con el certificado de su empresa (o el
     * global) y reemplaza el archivo en disco. NO corrompe el original: solo
     * lo reemplaza después de confirmar que el sidecar devolvió éxito, que
     * (si se pidió) aplicó la conformidad y que el archivo firmado existe.
     *
     * Espera un documento con digital_signature_status = 'pending' (el dueño
     * del archivo es este pipeline); si al cerrar el estado cambió o el
     * archivo fue reemplazado (otra version), descarta el resultado.
     *
     * @return array La metadata devuelta por el sidecar ([] si se descartó)
     * @throws DocumentSigningException Ante cualquier fallo de configuración,
     *                                   elegibilidad, o del propio sidecar.
     */
    public function signDocument(Document $document): array
    {
        $this->assertEligible($document);

        $certificate = $this->effectiveCertificateOrFail($document);

        if (!$document->fileExists()) {
            throw new DocumentSigningException(
                "El archivo del documento #{$document->id} no existe en disco.",
                404
            );
        }

        $disk = Storage::disk('documents');

        // ---- Preparar (bajo el lock): base y entrada del sidecar ----
        try {
            $prep = $this->withDocumentLock($document->id, fn () => $this->prepare($document));
        } catch (LockTimeoutException $e) {
            throw new DocumentSigningException('El documento está siendo modificado; se reintentará.', 503);
        }

        /** @var Document $doc */
        $doc = $prep['document'];
        $version = $doc->version;
        $path = $doc->file_path;

        $tempRelativePath = $this->buildTempPath($path);
        $tempAbsolutePath = $disk->path($tempRelativePath);
        $disk->makeDirectory(dirname($tempRelativePath));

        $payload = [
            'input_path' => $disk->path($prep['input']),
            'output_path' => $tempAbsolutePath,
            'certificate_path' => $certificate->absolutePath,
            'certificate_password' => $certificate->password,
            // Sello del pie con los datos del certificado: desactivado por defecto
            // (ver config/signature.php 'visible_stamp').
            'visible' => (bool) config('signature.visible_stamp'),
            // TSA de la empresa si el certificado es propio y la tiene; si no, la global.
            'tsa_url' => $certificate->tsaUrl ?? SignatureSettings::current()->tsa_url,
        ];

        if ($prep['first']) {
            $payload['base_output_path'] = $disk->path($doc->originalStoragePath());
        } else {
            $payload['skip_normalize'] = true;
        }

        if ($prep['conformity']) {
            $payload['conformity'] = $this->buildConformityPayload($doc);
        }

        $response = $this->postToSigner('/sign', $payload, $doc, function () use ($tempRelativePath) {
            $this->cleanupTemp($tempRelativePath);
        });

        $body = $response->json() ?? [];

        if ($response->failed() || !($body['success'] ?? false)) {
            $this->cleanupTemp($tempRelativePath);
            $error = $body['error'] ?? $body['message'] ?? "HTTP {$response->status()}";
            $stage = (string) ($body['stage'] ?? '');
            Log::error('[DocumentSigningService] El sidecar de firma reportó un fallo', [
                'document_id' => $doc->id,
                'http_status' => $response->status(),
                'stage' => $stage ?: null,
                'error' => $error,
            ]);

            if (preg_match('/tsa|timestamp|sello de tiempo/i', $stage . ' ' . $error)) {
                throw new DocumentSigningException("Sello de tiempo no disponible: {$error}");
            }

            throw new DocumentSigningException("No se pudo firmar el documento: {$error}");
        }

        $signature = $body['signature'] ?? [];

        // Un sidecar viejo (sin el contrato nuevo) no puede hacer pasar por
        // incluida una conformidad que no dibujó.
        if ($prep['conformity'] && ($signature['conformity_applied'] ?? false) !== true) {
            $this->cleanupTemp($tempRelativePath);
            throw new DocumentSigningException(
                'El firmador no confirmó haber aplicado la conformidad del trabajador.'
            );
        }

        if (!$disk->exists($tempRelativePath)) {
            throw new DocumentSigningException(
                'El firmador reportó éxito pero no se encontró el archivo firmado.'
            );
        }

        if ($prep['first'] && !$disk->exists($doc->originalStoragePath())) {
            $this->cleanupTemp($tempRelativePath);
            throw new DocumentSigningException(
                'El firmador no dejó la base de re-firma (.originals); no se reemplaza el archivo.'
            );
        }

        // ---- Cierre (bajo el lock + transacción) ----
        $requeue = false;
        $applied = false;

        try {
            $this->withDocumentLock($doc->id, function () use (
                $doc, $version, $path, $prep, $signature, $certificate, $tempRelativePath, $disk, &$requeue, &$applied
            ) {
                DB::transaction(function () use (
                    $doc, $version, $path, $prep, $signature, $certificate, $tempRelativePath, $disk, &$requeue, &$applied
                ) {
                    $fresh = Document::withoutGlobalScope(TenantFilterScope::class)
                        ->lockForUpdate()->find($doc->id);

                    // Reemplazo durante el job (otra version / ruta) o el
                    // documento ya no es nuestro: se descarta el temporal.
                    if (!$fresh
                        || $fresh->version !== $version
                        || $fresh->file_path !== $path
                        || $fresh->digital_signature_status !== 'pending') {
                        $this->cleanupTemp($tempRelativePath);
                        Log::info('[DocumentSigningService] Resultado de firma descartado (el documento cambió)', [
                            'document_id' => $doc->id,
                        ]);

                        return;
                    }

                    // Reemplazo atómico del archivo servido.
                    $disk->move($tempRelativePath, $fresh->file_path);

                    $previous = $fresh->digital_signature;
                    $includes = (bool) $prep['conformity'];

                    $attrs = ['file_size' => $disk->size($fresh->file_path)];
                    if ($prep['first']) {
                        $attrs['original_file_path'] = $fresh->originalStoragePath();
                        $attrs['original_has_conformity'] = $prep['original_has_conformity'];
                    }
                    $fresh->update($attrs);

                    $fresh->applyDigitalSignature([
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
                        // Sello visible y datos limpios del firmante. Un
                        // sidecar antiguo no los devuelve: false/null.
                        'stamp_applied' => (bool) ($signature['stamp_applied'] ?? false),
                        'signer_details' => is_array($signature['signer_details'] ?? null)
                            ? $signature['signer_details']
                            : null,
                        // Origen del certificado. NO se guarda aviso de RUC
                        // distinto: este JSON llega a empleados y admins.
                        'certificate_source' => $certificate->source,
                        'certificate_ruc' => $certificate->ruc,
                        'certificate_organization' => $certificate->organization,
                        // Conformidad del trabajador dibujada en este PDF.
                        'includes_conformity' => $includes,
                        'conformity_signed_at' => $includes ? $fresh->signed_at?->toIso8601String() : null,
                        // Se conserva la primera firma; resign_count cuenta las siguientes.
                        'first_signed_at' => $previous['first_signed_at']
                            ?? $signature['signing_time']
                            ?? now()->toIso8601String(),
                        'resign_count' => $previous === null ? 0 : ((int) ($previous['resign_count'] ?? 0)) + 1,
                    ]);

                    // ¿El trabajador firmó mientras el job corría? Entonces
                    // sigue 'pending' y se vuelve a encolar.
                    $fresh->refresh();
                    if ($this->needsSigning($fresh)) {
                        $requeue = true;
                    } else {
                        $fresh->forceFill([
                            'digital_signature_status' => 'signed',
                            'digital_signature_error' => null,
                        ])->save();
                    }

                    $applied = true;

                    $this->auditService->logDocumentDigitallySigned($fresh->id, null, [
                        'certificate_source' => $certificate->source,
                        'includes_conformity' => $includes,
                        'resign' => $previous !== null,
                        'tenant_id' => $fresh->tenant_id,
                    ]);
                });
            });
        } catch (LockTimeoutException $e) {
            $this->cleanupTemp($tempRelativePath);
            throw new DocumentSigningException('El documento está siendo modificado; se reintentará.', 503);
        }

        if ($requeue) {
            SignDocument::dispatch($doc->id)->onQueue('signing-priority');
        }

        if ($applied) {
            Log::info('[DocumentSigningService] Documento firmado digitalmente', [
                'document_id' => $doc->id,
                'signer_subject' => $signature['signer_subject'] ?? null,
                'tsa_applied' => $signature['tsa_applied'] ?? false,
                'certificate_source' => $certificate->source,
                'includes_conformity' => (bool) $prep['conformity'],
            ]);

            return $signature;
        }

        return [];
    }

    /**
     * Prepara la firma bajo el lock: relee la fila, resuelve la base
     * (extracción en el caso backfill) y decide qué enviar al sidecar.
     *
     * @return array{document: Document, input: string, first: bool, conformity: bool, original_has_conformity: bool}
     */
    private function prepare(Document $document): array
    {
        $disk = Storage::disk('documents');
        $doc = $this->findFresh($document->id) ?? $document;

        // Documento firmado con PAdES antes del cambio: aún no tiene base.
        if ($doc->original_file_path === null && $doc->digital_signature !== null) {
            $this->extractBase($doc);
            $doc = $this->findFresh($doc->id) ?? $doc;
        }

        if ($doc->original_file_path !== null) {
            if (!$disk->exists($doc->original_file_path)) {
                throw new DocumentSigningException(
                    "La base de re-firma del documento #{$doc->id} no existe en disco."
                );
            }

            return [
                'document' => $doc,
                'input' => $doc->original_file_path,
                'first' => false,
                'conformity' => $doc->isSigned() && !$doc->original_has_conformity,
                'original_has_conformity' => (bool) $doc->original_has_conformity,
            ];
        }

        // Primera firma: se normaliza desde file_path. Si el trabajador ya
        // firmó con FPDI (antes del certificado) el archivo ya trae su
        // nombre dibujado y no debe repetirse. FPDI no actualiza file_size
        // (tamaño del zip), así que un tamaño distinto indica que escribió.
        $hasConformity = $doc->isSigned() && (
            ($doc->signature['pdf_mark'] ?? null) === 'fpdi'
            || (($doc->signature['pdf_mark'] ?? null) === null
                && $disk->size($doc->file_path) !== (int) $doc->file_size)
        );

        $disk->makeDirectory(dirname($doc->originalStoragePath()));

        return [
            'document' => $doc,
            'input' => $doc->file_path,
            'first' => true,
            'conformity' => $doc->isSigned() && !$hasConformity,
            'original_has_conformity' => $hasConformity,
        ];
    }

    /**
     * Caso D (backfill): recupera la revisión 0 de un PDF ya firmado con el
     * sidecar (POST /extract-base) y deja original_file_path apuntándola.
     *
     * @throws DocumentSigningException
     */
    private function extractBase(Document $doc): void
    {
        $disk = Storage::disk('documents');
        $relative = $doc->originalStoragePath();
        $disk->makeDirectory(dirname($relative));

        $response = $this->postToSigner('/extract-base', [
            'input_path' => $disk->path($doc->file_path),
            'output_path' => $disk->path($relative),
        ], $doc);

        $body = $response->json() ?? [];

        if ($response->failed() || !($body['success'] ?? false) || !$disk->exists($relative)) {
            $error = $body['error'] ?? $body['message'] ?? "HTTP {$response->status()}";
            Log::error('[DocumentSigningService] El sidecar no pudo extraer la base de re-firma', [
                'document_id' => $doc->id,
                'stage' => $body['stage'] ?? null,
                'error' => $error,
            ]);
            throw new DocumentSigningException("No se pudo recuperar la base del documento firmado: {$error}");
        }

        $doc->forceFill([
            'original_file_path' => $relative,
            'original_has_conformity' => false,
        ])->save();
    }

    /**
     * Datos de la conformidad del trabajador que el sidecar dibuja en el PDF.
     * El layout sale de config/signature.php (única fuente) según el tamaño
     * de página del lote, igual que el flujo FPDI.
     */
    private function buildConformityPayload(Document $doc): array
    {
        // Elección explícita = batch con page_size en la columna cruda (NO
        // resolved_page_size, que rellena 'a10'). Sin elección el formato se
        // detecta por el tamaño real de la página (en el sidecar).
        $explicit = $doc->batch?->page_size;
        $sizes = config('signature.watermark.sizes', []);
        $default = config('signature.watermark.default_size', 'a10');
        $key = $explicit ?: $default;
        if (!isset($sizes[$key])) {
            $key = $default;
        }

        $signature = $doc->signature ?? [];

        $payload = [
            'name' => $signature['user_name'] ?? 'FIRMADO CONFORME',
            'date_text' => $this->pdfWatermarkService->formatTimestamp(
                $signature['timestamp'] ?? ($doc->signed_at?->toISOString() ?? now()->toISOString())
            ),
            'layout' => SignatureLayout::sidecarLayout($key),
        ];

        if (!$explicit) {
            $payload['layouts'] = collect(array_keys($sizes))
                ->mapWithKeys(fn ($k) => [$k => SignatureLayout::sidecarLayout($k)])
                ->all();
            $payload['page_dimensions_mm'] = config('signature.watermark.page_dimensions_mm', []);
        }

        return $payload;
    }

    /**
     * POST al sidecar. Un fallo de conexión se traduce a
     * DocumentSigningException(503) (transitorio, reintentable).
     */
    private function postToSigner(string $endpoint, array $payload, Document $doc, ?callable $onConnectionFailure = null)
    {
        try {
            return Http::baseUrl(config('services.signer.base_url'))
                ->timeout((int) config('services.signer.timeout', 120))
                ->connectTimeout((int) config('services.signer.connect_timeout', 10))
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            if ($onConnectionFailure) {
                $onConnectionFailure();
            }
            Log::error('[DocumentSigningService] No se pudo conectar al sidecar de firma', [
                'document_id' => $doc->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new DocumentSigningException(
                "No se pudo conectar al servicio de firma digital: {$e->getMessage()}",
                503
            );
        }
    }

    // ------------------------------------------------------------------
    // Fallo y respaldo FPDI
    // ------------------------------------------------------------------

    /**
     * Regla de fallo (failed() del job y rama no elegible): escribe 'failed'
     * solo si el documento sigue 'pending' y aún necesita firmarse, de modo
     * que un job viejo nunca pisa un 'signed' posterior. Si nunca hubo PAdES y
     * el trabajador ya firmó (conformidad sin marca en el PDF), aplica el
     * respaldo FPDI.
     */
    public function markFailedIfOwned(Document $document, string $error): void
    {
        try {
            $this->withDocumentLock($document->id, function () use ($document, $error) {
                $fresh = $this->findFresh($document->id);

                if (!$fresh
                    || $fresh->digital_signature_status !== 'pending'
                    || !$this->needsSigning($fresh)) {
                    return;
                }

                if ($fresh->digital_signature === null && $fresh->isSigned() && !$fresh->original_has_conformity) {
                    $this->fpdiFallback($fresh);
                }

                Document::withoutGlobalScope(TenantFilterScope::class)
                    ->where('id', $fresh->id)
                    ->where('digital_signature_status', 'pending')
                    ->update([
                        'digital_signature_status' => 'failed',
                        'digital_signature_error' => Str::limit($error, 497),
                    ]);
            });
        } catch (LockTimeoutException $e) {
            Log::error('[DocumentSigningService] No se pudo marcar el fallo de firma: documento ocupado', [
                'document_id' => $document->id,
            ]);
        }
    }

    /**
     * Respaldo: sin PAdES posible, la conformidad del trabajador se estampa
     * con FPDI sobre el archivo crudo (el invariante de 'pending' se rompe a
     * propósito justo antes de marcar 'failed'). Debe llamarse bajo el lock.
     */
    private function fpdiFallback(Document $document): void
    {
        $signature = $document->signature ?? [];

        // 'fpdi' / 'fpdi_failed': ya se intentó, no se repite (no duplicar el nombre).
        if (in_array($signature['pdf_mark'] ?? null, ['fpdi', 'fpdi_failed'], true)) {
            return;
        }

        // Firmado con FPDI antes de que existiera pdf_mark (pdf_mark null):
        // FPDI no actualiza file_size, así que un tamaño distinto indica que
        // el nombre ya está dibujado (misma regla que prepare()). No se
        // vuelve a estampar; se deja constancia para que la re-firma no lo
        // repita.
        if (($signature['pdf_mark'] ?? null) === null
            && $document->file_path
            && Storage::disk('documents')->exists($document->file_path)
            && Storage::disk('documents')->size($document->file_path) !== (int) $document->file_size) {
            $signature['pdf_mark'] = 'fpdi';
            $document->forceFill([
                'signature' => $signature,
                'original_has_conformity' => true,
            ])->save();

            return;
        }

        $signature['pdf_mark'] = $this->applyFpdiMark($document, $signature);
        $document->forceFill(['signature' => $signature])->save();
    }

    /**
     * Estampa el nombre del trabajador con FPDI sobre file_path (archivo
     * crudo, sin PAdES). Devuelve el valor de signature.pdf_mark.
     * Debe llamarse bajo el lock del documento.
     */
    public function applyFpdiMark(Document $document, array $signatureData): string
    {
        if (!$document->file_path) {
            return 'fpdi_failed';
        }

        try {
            $applied = $this->pdfWatermarkService->addSignatureWatermark(
                $document->file_path,
                $signatureData,
                $document->batch?->page_size ?: null
            );
        } catch (\Throwable $e) {
            Log::warning('[DocumentSigningService] FPDI lanzó una excepción', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            $applied = false;
        }

        if (!$applied) {
            Log::warning('[DocumentSigningService] Watermark could not be applied but document was signed', [
                'document_id' => $document->id,
            ]);
        }

        return $applied ? 'fpdi' : 'fpdi_failed';
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
     * Resuelve el certificado efectivo (empresa -> global) o falla de forma
     * PERMANENTE si no hay, si su binario falta o si ya venció.
     *
     * @throws DocumentSigningException
     */
    private function effectiveCertificateOrFail(Document $document): EffectiveCertificate
    {
        try {
            $certificate = $this->certificateService->resolveForTenant($document->tenant_id);
        } catch (CertificateUnavailableException $e) {
            throw new DocumentSigningException($e->getMessage());
        }

        if (!$certificate) {
            throw new DocumentSigningException(
                'No hay un certificado de firma digital disponible para la empresa del documento ni un certificado global.'
            );
        }

        if ($certificate->expiresAt && $certificate->expiresAt->isPast()) {
            $quien = $certificate->source === 'tenant' ? 'de la empresa' : 'global';
            throw new DocumentSigningException("El certificado de firma {$quien} venció el "
                . $certificate->expiresAt->copy()->timezone('America/Lima')->format('d/m/Y') . '.');
        }

        return $certificate;
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
