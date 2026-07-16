<?php

namespace App\Jobs;

use App\Mail\NewDocumentAvailableMail;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Services\NotificationService;
use App\Services\TenantMailerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBatchNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DocumentBatch $batch
    ) {
    }

    /**
     * Execute the job.
     *
     * El mailer de cada documento se resuelve por su tenant AQUÍ, dentro
     * del handle() que ya corre en el worker (este job implementa
     * ShouldQueue). NewDocumentAvailableMail también implementa
     * ShouldQueue, por eso se envía vía TenantMailerService::send(), que
     * usa sendNow() para entregarlo de forma síncrona en este mismo
     * proceso en vez de volver a encolarlo (ver docblock de
     * TenantMailerService).
     */
    public function handle(NotificationService $notificationService, TenantMailerService $tenantMailerService): void
    {
        Log::info("SendBatchNotifications: Enviando notificaciones para batch {$this->batch->id}");

        // Obtener documentos no huérfanos y no notificados de este batch
        $documents = Document::where('batch_id', $this->batch->id)
            ->where('status', '!=', 'orphan')
            ->where('notified', false)
            ->with(['user', 'documentType', 'tenant'])
            ->get();

        $notifiedCount = 0;

        foreach ($documents as $document) {
            if (!$document->user || !$document->user->email) {
                continue;
            }

            try {
                // In-app notification
                $documentName = $document->documentType?->display_name ?? 'Documento';
                $notificationService->notifyNewDocument(
                    userId: $document->user_id,
                    documentId: $document->id,
                    documentName: "{$documentName} - {$document->period}",
                    tenantId: $document->tenant_id
                );
            } catch (\Exception $e) {
                Log::warning("SendBatchNotifications: Error creando notificación in-app para usuario {$document->user_id}: {$e->getMessage()}");
            }

            try {
                // Email notification (enrutado por el mailer propio de la
                // empresa del documento, con fallback al de la plataforma)
                $tenantMailerService->send(
                    $document->tenant,
                    $document->user->email,
                    new NewDocumentAvailableMail($document)
                );

                $document->markAsNotified();
                $notifiedCount++;

            } catch (\Exception $e) {
                Log::error("SendBatchNotifications: Error enviando email a {$document->user->email}: {$e->getMessage()}");
            }
        }

        // Marcar batch como notificaciones enviadas
        $this->batch->update(['notifications_sent' => true]);

        Log::info("SendBatchNotifications: {$notifiedCount} notificaciones enviadas para batch {$this->batch->id}");
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SendBatchNotifications: Job fallido para batch {$this->batch->id}: {$exception->getMessage()}");
    }
}

