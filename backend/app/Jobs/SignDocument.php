<?php

namespace App\Jobs;

use App\Exceptions\DocumentSigningException;
use App\Models\Document;
use App\Services\DocumentSigningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Firma un Document con el certificado CRIPTOGRÁFICO de plataforma (PAdES,
 * vía el sidecar HTTP `signer`), disparado:
 *   - Automáticamente desde ProcessDocumentChunk cuando el documento quedó
 *     'pending' por requires_signature y SignatureSettings::signature_enabled.
 *   - On-demand desde el endpoint de firma manual (root/admin), ver
 *     DocumentController::signDigital.
 *
 * Recibe el ID (no el modelo) para no serializar un Document potencialmente
 * desactualizado si el job queda en cola un rato; se recarga fresco en handle().
 */
class SignDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * El reintento real de fallos transitorios (sidecar caído, TSA sin
     * responder) lo maneja este contador; los reintentos NO tienen sentido
     * para condiciones permanentes (ya firmado, firma deshabilitada), que
     * se detectan antes de intentar y no cuentan como fallo del job.
     */
    public int $tries = 3;

    /**
     * Backoff progresivo: da tiempo a que un sidecar caído o una TSA con
     * problemas se recuperen antes del siguiente intento.
     */
    public array $backoff = [30, 120, 300];

    /**
     * Debe superar el timeout HTTP hacia `signer` (config('services.signer.timeout'),
     * default 120s) con margen para el resto del trabajo (I/O de Storage).
     */
    public int $timeout = 180;

    public function __construct(public int $documentId)
    {
    }

    public function handle(DocumentSigningService $service): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            Log::warning("SignDocument: documento {$this->documentId} no encontrado (¿eliminado?); se omite.");
            return;
        }

        // Elegibilidad primero: si es una condición PERMANENTE (ya firmado,
        // firma deshabilitada, no requiere firma, huérfano), no es un fallo
        // del job -se registra y se sale sin lanzar, para que Horizon no lo
        // cuente como intento fallido ni lo reintente.
        try {
            $service->assertEligible($document);
        } catch (DocumentSigningException $e) {
            Log::info("SignDocument: documento {$this->documentId} no elegible para firmar, se omite: {$e->getMessage()}");
            return;
        }

        try {
            $signature = $service->signDocument($document);
        } catch (DocumentSigningException $e) {
            Log::error("SignDocument: fallo firmando documento {$this->documentId} (intento {$this->attempts()}): {$e->getMessage()}");
            // Relanzar para que Horizon aplique $tries/$backoff. Si se
            // agotan los intentos, failed() deja registro final y el
            // documento queda 'pending' (reintentable manualmente via el
            // endpoint on-demand).
            throw $e;
        }

        Log::info("SignDocument: documento {$this->documentId} firmado exitosamente", [
            'signer_subject' => $signature['signer_subject'] ?? null,
        ]);
    }

    /**
     * Handle a job failure (se agotaron los reintentos).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SignDocument: fallo definitivo firmando documento {$this->documentId} tras {$this->tries} intentos: {$exception->getMessage()}");
        // Deliberadamente NO se cambia el status del Document: queda
        // 'pending' (requires_signature=true), visible para reintento
        // manual vía el endpoint on-demand de firma.
    }
}
