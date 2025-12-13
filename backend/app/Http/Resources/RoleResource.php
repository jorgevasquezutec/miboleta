<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'display_name' => $this->display_name,
            'description' => $this->description,
            'granted_at' => $this->whenPivotLoaded('role_user', function () {
                return $this->pivot->granted_at;
            }),
            'granted_by' => $this->whenPivotLoaded('role_user', function () {
                return $this->pivot->granted_by;
            }),
        ];
    }
}
