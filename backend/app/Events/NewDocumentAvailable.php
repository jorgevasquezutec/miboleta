<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDocumentAvailable implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $documentData;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Document $document
    ) {
        $this->documentData = [
            'id' => $document->id,
            'type' => $document->documentType->display_name ?? 'Documento',
            'period' => $document->period,
            'requires_signature' => $document->requires_signature,
            'status' => $document->status,
            'created_at' => $document->created_at->toISOString(),
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Canal del usuario (si tiene usuario asignado)
        if ($this->document->user_id) {
            $channels[] = new PrivateChannel('user.' . $this->document->user_id);
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'document.available';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'document' => $this->documentData,
            'message' => 'Nuevo documento disponible: ' . $this->documentData['type'],
        ];
    }
}
