<?php

namespace App\Events;

use App\Models\DocumentBatch;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BatchProgress implements ShouldBroadcast
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
            'processed_files' => $batch->processed_files,
            'success_count' => $batch->success_count,
            'replaced_count' => $batch->replaced_count,
            'orphan_count' => $batch->orphan_count,
            'error_count' => $batch->error_count,
            'progress_percentage' => $batch->progress_percentage,
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
        return 'batch.progress';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'batch' => $this->batchData,
        ];
    }
}
