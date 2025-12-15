<?php

namespace App\Jobs;

use App\Mail\NewDocumentAvailableMail;
use App\Models\Document;
use App\Models\DocumentBatch;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     */
    public function handle(NotificationService $notificationService): void
    {
        Log::info("SendBatchNotifications: Enviando notificaciones para batch {$this->batch->id}");

        // Obtener documentos no huérfanos y no notificados de este batch
        $documents = Document::where('batch_id', $this->batch->id)
            ->where('status', '!=', 'orphan')
            ->where('notified', false)
            ->with(['user', 'documentType'])
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
                // Email notification
                Mail::to($document->user->email)->send(
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

