<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Contador de empleados (RP1-C): initial_employee_count se fija una
        // sola vez (idempotente) al completar la primera carga masiva sobre
        // el tenant (ver UserBatch::syncInitialEmployeeCounts).
        $currentEmployeeCount = $this->users()->count();
        $initialEmployeeCount = (int) ($this->initial_employee_count ?? 0);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'ruc' => $this->ruc,
            'business_name' => $this->business_name,
            'logo_url' => $this->logo_url,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'status' => $this->status,
            'initial_employee_count' => $initialEmployeeCount,
            'current_employee_count' => $currentEmployeeCount,
            'subsequent_employee_count' => max(0, $currentEmployeeCount - $initialEmployeeCount),
            // Régimen laboral para el cómputo de vacaciones (RP2-A)
            'labor_regime' => $this->labor_regime,
            // Configuración SMTP propia de la empresa (RP2-B). mail_password
            // NUNCA se expone; solo un booleano que indica si ya hay una
            // guardada (ver Tenant::$hidden y TenantMailerService).
            'mail_host' => $this->mail_host,
            'mail_port' => $this->mail_port,
            'mail_username' => $this->mail_username,
            'mail_encryption' => $this->mail_encryption,
            'mail_from_address' => $this->mail_from_address,
            'mail_from_name' => $this->mail_from_name,
            'has_mail_password' => !empty($this->mail_password),
            'has_custom_mailer' => $this->hasCustomMailer(),
            'is_primary' => $this->whenPivotLoaded('user_tenants', function () {
                return (bool) $this->pivot->is_primary;
            }),
            'supervisor_id' => $this->whenPivotLoaded('user_tenants', function () {
                return $this->pivot->supervisor_id;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
