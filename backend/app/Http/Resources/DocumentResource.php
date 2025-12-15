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
            'user_id' => $this->user_id,
            'tenant_id' => $this->tenant_id,
            'batch_id' => $this->batch_id,
            'doc_type_id' => $this->doc_type_id,
            'document_type' => new DocumentTypeResource($this->whenLoaded('documentType')),
            'document_type_id' => $this->document_type_id,
            'period' => $this->period,
            'status' => $this->status,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'original_name' => $this->original_name,
            'original_filename' => $this->original_filename,
            'employee_document_number' => $this->employee_document_number,
            'uploaded_by' => $this->uploaded_by,
            'requires_signature' => $this->requires_signature,
            'signature' => $this->signature,
            'signed_at' => $this->signed_at,
            'signature_ip' => $this->signature_ip,
            'expires_at' => $this->expires_at,
            'notified' => $this->notified,
            'notified_at' => $this->notified_at,
            'version' => $this->version,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'batch' => new DocumentBatchResource($this->whenLoaded('batch')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
