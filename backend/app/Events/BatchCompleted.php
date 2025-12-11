<?php

namespace App\Events;

use App\Models\DocumentBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $batchData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public DocumentBatch $batch
    ) {
        $this->batchData = [
            'id' => $batch->id,
            'status' => $batch->status,
            'total_files' => $batch->total_files,
            'success_count' => $batch->success_count,
            'replaced_count' => $batch->replaced_count,
            'orphan_count' => $batch->orphan_count,
            'error_count' => $batch->error_count,
            'started_at' => $batch->started_at?->toISOString(),
            'completed_at' => $batch->completed_at?->toISOString(),
            'document_type' => $batch->documentType->display_name ?? 'Documentos',
            'period' => $batch->period,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->batch->tenant_id),
            new PrivateChannel('batch.' . $this->batch->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'batch.completed';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        $message = $this->batch->status === 'completed'
            ? "Carga completada: {$this->batchData['success_count']} documentos procesados"
            : "Carga completada con errores: {$this->batchData['error_count']} errores";

        return [
            'batch' => $this->batchData,
            'message' => $message,
        ];
    }
}
