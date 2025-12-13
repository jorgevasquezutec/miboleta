<?php

namespace App\Jobs\Callbacks;

use App\Events\BatchCompleted;
use App\Events\NewDocumentAvailable;
use App\Jobs\SendBatchNotifications;
use App\Models\Document;
use App\Models\DocumentBatch;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Log;

class BatchCompletedCallback
{
    public function __construct(
        public DocumentBatch $batch
    ) {
    }

    public function __invoke(Batch $laravelBatch): void
    {
        Log::info("BatchCompletedCallback: Laravel Batch {$laravelBatch->id} completado");

        // Marcar como completado
        $this->batch->markAsCompleted();

        // Broadcast evento de completado
        broadcast(new BatchCompleted($this->batch->fresh()));

        // Si se debe notificar a empleados, disparar job de notificaciones
        if ($this->batch->notify_employees) {
            SendBatchNotifications::dispatch($this->batch)->onQueue('notifications');
        }

        // Broadcast eventos individuales a cada usuario con documento nuevo
        $documents = Document::where('batch_id', $this->batch->id)
            ->where('status', '!=', 'orphan')
            ->whereNotNull('user_id')
            ->with('documentType')
            ->get();

        foreach ($documents as $document) {
            broadcast(new NewDocumentAvailable($document));
        }

        Log::info("BatchCompletedCallback: Batch {$this->batch->id} completado - " .
            "Éxito: {$this->batch->success_count}, " .
            "Reemplazados: {$this->batch->replaced_count}, " .
            "Huérfanos: {$this->batch->orphan_count}, " .
            "Errores: {$this->batch->error_count}");
    }
}
