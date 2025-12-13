<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
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
            'file_path' => $this->file_path,
            'original_filename' => $this->original_filename,
            'employee_document_number' => $this->employee_document_number,
            'requires_signature' => $this->requires_signature,
            'signed_at' => $this->signed_at,
            'signature_ip' => $this->signature_ip,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'batch' => new DocumentBatchResource($this->whenLoaded('batch')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
