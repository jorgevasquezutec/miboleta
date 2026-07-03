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
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
            'role' => $this->getCurrentRole(),
            'roles' => $this->getCurrentRoles(),
            'tenants' => $this->when($this->relationLoaded('tenants'), function () use ($request) {
                // 🔒 SECURITY: Filter tenants to only show those the current user has access to
                $currentUser = $request->user();
                $visibleTenants = $this->tenants;

                if ($currentUser && !$currentUser->isRoot()) {
                    // Non-root users can only see tenants they have access to
                    $allowedTenantIds = $currentUser->tenants->pluck('id')->toArray();
                    $visibleTenants = $this->tenants->filter(function ($tenant) use ($allowedTenantIds) {
                        return in_array($tenant->id, $allowedTenantIds);
                    });
                }

                // Roles por empresa (user_tenant_roles), agrupados una sola vez
                // para evitar N+1 al mapear cada tenant de más abajo.
                $tenantRolesByTenant = $this->relationLoaded('tenantRoles')
                    ? $this->tenantRoles->groupBy('tenant_id')
                    : collect();

                return $visibleTenants->map(function ($tenant) use ($tenantRolesByTenant) {
                    $supervisorId = $tenant->pivot->supervisor_id ?? null;
                    $supervisor = null;

                    // Cargar información del supervisor si existe
                    if ($supervisorId) {
                        $supervisorUser = \App\Models\User::find($supervisorId);
                        if ($supervisorUser) {
                            $supervisor = [
                                'id' => $supervisorUser->id,
                                'name' => $supervisorUser->name,
                                'full_name' => $supervisorUser->full_name,
                                'email' => $supervisorUser->email,
                            ];
                        }
                    }

                    $tenantRoles = $tenantRolesByTenant->get($tenant->id) ?? collect();
                    $tenantRoleNames = $tenantRoles->pluck('role.name')->filter()->values();
                    $tenantRoleIds = $tenantRoles->pluck('role_id')->filter()->values();

                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'ruc' => $tenant->ruc,
                        'is_primary' => $tenant->pivot->is_primary ?? false,
                        'supervisor_id' => $supervisorId,
                        'supervisor' => $supervisor,
                        // Roles operativos del usuario en esta empresa: base para
                        // pre-seleccionar el multi-select de UserFormPage.
                        'roles' => $tenantRoleNames,
                        'role' => \App\Models\User::highestPriorityRole($tenantRoleNames),
                        'role_ids' => $tenantRoleIds,
                        'hire_date' => $tenant->pivot->hire_date
                            ? \Illuminate\Support\Carbon::parse($tenant->pivot->hire_date)->format('Y-m-d')
                            : null,
                        'vacation_balance_initial' => $tenant->pivot->vacation_balance_initial,
                    ];
                })->values();  // ✅ Reset array keys after filter
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
