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
            'tenants' => $this->when($this->relationLoaded('tenants'), function () {
                return $this->tenants->map(function ($tenant) {
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'ruc' => $tenant->ruc,
                        'is_primary' => $tenant->pivot->is_primary ?? false,
                        'supervisor_id' => $tenant->pivot->supervisor_id ?? null,
                    ];
                });
            }),
            'primary_tenant' => $this->when($this->primaryTenant(), function () {
                return new TenantResource($this->primaryTenant());
            }),
            'avatar_url' => $this->avatar_url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'last_login_at' => $this->last_login_at,
        ];
    }
}
