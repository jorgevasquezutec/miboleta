<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => new DocumentTypeResource($this->whenLoaded('documentType')),
            'document_type_id' => $this->document_type_id,
            'period' => $this->period,
            'status' => $this->status,
            'total_files' => $this->total_files,
            'processed_files' => $this->processed_files,
            'successful_files' => $this->successful_files,
            'failed_files' => $this->failed_files,
            'progress_percentage' => $this->progress_percentage,
            'original_filename' => $this->original_filename,
            'error_message' => $this->error_message,
            'uploaded_by' => new UserSummaryResource($this->whenLoaded('uploadedBy')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
