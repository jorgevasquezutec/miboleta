<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'document_type' => $this->document_type,
            'document_text' => $this->document_text,
            'phone' => $this->phone,
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
            'role' => $this->getCurrentRole(),
            'roles' => $this->getCurrentRoles(),
            'tenants' => TenantResource::collection($this->whenLoaded('tenants')),
            'primary_tenant' => $this->when($this->primaryTenant(), function () {
                return new TenantResource($this->primaryTenant());
            }),
            'immediate_supervisor' => new UserSummaryResource($this->whenLoaded('immediateSupervisor')),
            'subordinates' => UserSummaryResource::collection($this->whenLoaded('subordinates')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
