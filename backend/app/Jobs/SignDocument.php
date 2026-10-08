<?php

namespace App\Jobs;

use App\Exceptions\DocumentSigningException;
use App\Models\Document;
use App\Models\Scopes\TenantFilterScope;
use App\Services\DocumentSigningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Firma (o re-firma) digitalmente con PAdES un Document con el certificado de
 * su empresa o, si no tiene uno propio, el global de la plataforma (vía el
 * sidecar HTTP `signer`). Disparado:
 *   - Automáticamente desde ProcessDocumentChunk / asignación de huérfano
 *     (cola 'signing').
 *   - Cuando el trabajador da su conformidad (2FA): hay que re-generar el PDF
 *     firmado con su nombre incluido (cola 'signing-priority').
 *   - On-demand / "Reintentar" desde DocumentController::signDigital
 *     (cola 'signing-priority').
 *
 * Solo toca las columnas digital_* del documento (ver DocumentSigningService);
 * NUNCA su `status`. Es idempotente: decide qué hacer según el estado fresco de
 * la fila, no según quien lo encoló.
 *
 * Recibe el ID (no el modelo) para no serializar un Document potencialmente
 * desactualizado si el job queda en cola un rato; se recarga fresco en handle().
 */
class SignDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Conexión dedicada (config/queue.php): retry_after=300 > $timeout. */
    public const CONNECTION = 'redis-signing';

    /**
     * Sin límite de intentos "totales": las liberaciones por solapamiento
     * (WithoutOverlapping) no deben consumirlos. Lo que acota los reintentos
     * reales es $maxExceptions (3 excepciones) y retryUntil() (30 min).
     */
    public int $tries = 0;

    public int $maxExceptions = 3;

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
        // En tests/local con QUEUE_CONNECTION=sync se respeta 'sync' (no hay Redis).
        if (config('queue.default') !== 'sync') {
            $this->onConnection(self::CONNECTION);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    /**
     * Un job por documento a la vez; si hay otro corriendo se libera a los
     * 30s sin consumir intentos.
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("document-signing:{$this->documentId}"))
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function handle(DocumentSigningService $service): void
    {
        $fresh = $service->findFresh($this->documentId);

        if (!$fresh) {
            Log::warning("SignDocument: documento {$this->documentId} no encontrado (¿eliminado?); se omite.");
            return;
        }

        // Si ya no está 'pending' el archivo dejó de ser de este pipeline
        // (reemplazo, ya firmado por otro job): nada que hacer.
        if ($fresh->digital_signature_status !== 'pending') {
            Log::info("SignDocument: documento {$this->documentId} ya no está pendiente de firma digital; se omite.");
            return;
        }

        // Idempotencia: el PDF servido ya está al día.
        if (!$service->needsSigning($fresh)) {
            Document::withoutGlobalScope(TenantFilterScope::class)
                ->where('id', $fresh->id)
                ->where('digital_signature_status', 'pending')
                ->update(['digital_signature_status' => 'signed', 'digital_signature_error' => null]);
            return;
        }

        // Elegibilidad: si es una condición PERMANENTE (certificado ausente o
        // vencido, firmante distinto, firma deshabilitada en una primera
        // firma) no se reintenta: se aplica la regla de fallo y se sale.
        try {
            $service->assertEligible($fresh);
        } catch (DocumentSigningException $e) {
            Log::info("SignDocument: documento {$this->documentId} no elegible para firmar: {$e->getMessage()}");
            $service->markFailedIfOwned($fresh, $e->getMessage());
            return;
        }

        try {
            $signature = $service->signDocument($fresh);
        } catch (DocumentSigningException $e) {
            Log::error("SignDocument: fallo firmando documento {$this->documentId} (intento {$this->attempts()}): {$e->getMessage()}");
            // Relanzar para que Horizon aplique $maxExceptions/$backoff. Si
            // se agotan, failed() deja el documento en 'failed' (el PDF válido
            // anterior se sigue sirviendo).
            throw $e;
        }

        if ($signature !== []) {
            Log::info("SignDocument: documento {$this->documentId} firmado exitosamente", [
                'signer_subject' => $signature['signer_subject'] ?? null,
            ]);
        }
    }

    /**
     * Handle a job failure (se agotaron los reintentos).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SignDocument: fallo definitivo firmando documento {$this->documentId}: {$exception->getMessage()}");

        try {
            $service = app(DocumentSigningService::class);
            $fresh = $service->findFresh($this->documentId);

            if ($fresh) {
                // Solo marca 'failed' si sigue 'pending' y necesita firma; un
                // job viejo nunca pisa un 'signed' posterior. Sin PAdES previo
                // y con el trabajador firmado, aplica el respaldo FPDI.
                $service->markFailedIfOwned($fresh, $exception->getMessage());
            }
        } catch (\Throwable $e) {
            Log::error("SignDocument: no se pudo registrar el fallo del documento {$this->documentId}: {$e->getMessage()}");
        }
    }
}
