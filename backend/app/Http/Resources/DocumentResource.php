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
        $canSeeDigitalDetail = (bool) $request->user()?->can('documents.sign_digital', $this->tenant_id);

        // document_sha256 (hash de lo que vio el trabajador) es detalle de
        // soporte: solo lo ve quien puede firmar digitalmente.
        $signature = $this->signature;
        if (is_array($signature) && !$canSeeDigitalDetail) {
            unset($signature['document_sha256']);
        }

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
            'signature' => $signature,
            'signed_at' => $this->signed_at,
            // Firma digital (PAdES) de la empresa: independiente de la
            // conformidad del trabajador (status/signature/signed_at).
            'digital_signature' => $this->digital_signature,
            'digitally_signed_at' => $this->digitally_signed_at,
            'digital_signature_status' => $this->digital_signature_status,
            // El detalle del error solo lo ve quien puede firmar digitalmente.
            'digital_signature_error' => $canSeeDigitalDetail ? $this->digital_signature_error : null,
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
