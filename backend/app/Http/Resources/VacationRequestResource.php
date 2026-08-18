<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VacationRequestResource extends JsonResource
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
            // `&& $this->user` además de relationLoaded: si el solicitante
            // fue eliminado (soft delete), la relación se marca como cargada
            // pero resuelve a null por el scope global, y los accesos de
            // abajo reventaban al listar. Misma guarda que ya tenía 'approver'.
            'user' => $this->when($this->relationLoaded('user') && $this->user, function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'last_name' => $this->user->last_name,
                    'full_name' => $this->user->full_name,
                    'email' => $this->user->email,
                    'document_text' => $this->user->document_text,
                ];
            }),
            'tenant_id' => $this->tenant_id,
            'start_date' => $this->start_date->format('Y-m-d'),
            'end_date' => $this->end_date->format('Y-m-d'),
            'days_requested' => (float) $this->days_requested,
            'reason' => $this->reason,
            'status' => $this->status,
            'status_label' => $this->status_label,

            // Aprobador asignado actualmente (supervisor del empleado para
            // esta empresa). No es un snapshot histórico: si el supervisor
            // cambia después de creada la solicitud, refleja el vigente.
            'approver' => $this->when($this->relationLoaded('user') && $this->user, function () {
                $approver = $this->user->getSupervisorForTenant($this->tenant_id);

                return $approver ? [
                    'id' => $approver->id,
                    'full_name' => $approver->full_name,
                    'email' => $approver->email,
                ] : null;
            }),

            // Saldo de vacaciones del EMPLEADO SOLICITANTE para la empresa de
            // ESTA solicitud (no la activa del switcher: el saldo es por
            // empresa). Lo adjunta VacationRequestController::
            // attachVacationBalances() solo en los listados del aprobador
            // (pending-approval / pending-confirmation / my-decisions); en el
            // resto de endpoints la clave no aparece.
            'vacation_balance' => $this->when(!is_null($this->vacation_balance), fn () => [
                'tenant_id' => $this->vacation_balance['tenant_id'],
                'days_per_year' => $this->vacation_balance['days_per_year'],
                'pending' => $this->vacation_balance['pending'],
                'taken' => $this->vacation_balance['taken'],
                'truncated' => $this->vacation_balance['truncated'],
                'balance' => $this->vacation_balance['balance'],
            ]),

            // Approval info
            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->when($this->relationLoaded('approvedByUser') && $this->approvedByUser, function () {
                return [
                    'id' => $this->approvedByUser->id,
                    'full_name' => $this->approvedByUser->full_name,
                ];
            }),
            'approved_at' => $this->approved_at?->toISOString(),

            // Rejection info
            'rejected_by' => $this->rejected_by,
            'rejected_by_user' => $this->when($this->relationLoaded('rejectedByUser') && $this->rejectedByUser, function () {
                return [
                    'id' => $this->rejectedByUser->id,
                    'full_name' => $this->rejectedByUser->full_name,
                ];
            }),
            'rejected_at' => $this->rejected_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,

            // Confirmation info (was taken?)
            'was_taken' => $this->was_taken,
            'taken_label' => $this->taken_label,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_by_user' => $this->when($this->relationLoaded('confirmedByUser') && $this->confirmedByUser, function () {
                return [
                    'id' => $this->confirmedByUser->id,
                    'full_name' => $this->confirmedByUser->full_name,
                ];
            }),
            'confirmed_at' => $this->confirmed_at?->toISOString(),

            // Computed attributes
            'duration_text' => $this->duration_text,
            'date_range' => $this->date_range,

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
