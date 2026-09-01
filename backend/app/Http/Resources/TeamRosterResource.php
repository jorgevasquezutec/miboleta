<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fila del directorio "Mi Equipo" (ítem 43): un subordinado del supervisor
 * autenticado en la empresa activa, con su saldo de vacaciones y estado
 * actual/próximo.
 *
 * Espera envolver un User obtenido de User::subordinatesForTenant() (pivot
 * con position/department cargado) al que
 * VacationRequestController::myTeamRoster() le colgó tres atributos
 * dinámicos — roster_balance / roster_is_on_vacation_now /
 * roster_next_pending_request — ya resueltos EN LOTE (mismo patrón que
 * VacationRequestResource con `vacation_balance`, ver
 * VacationRequestController::attachVacationBalances()). Esta clase solo
 * serializa; no calcula nada ni golpea la BD.
 */
class TeamRosterResource extends JsonResource
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
            'full_name' => $this->full_name,
            'email' => $this->email,
            'position' => $this->pivot->position ?? null,
            'department' => $this->pivot->department ?? null,
            'avatar_url' => $this->avatar_url,
            'balance' => $this->roster_balance ? [
                'pending' => $this->roster_balance['pending'],
                'taken' => $this->roster_balance['taken'],
                'truncated' => $this->roster_balance['truncated'],
                'balance' => $this->roster_balance['balance'],
            ] : null,
            'is_on_vacation_now' => (bool) $this->roster_is_on_vacation_now,
            'next_pending_request' => $this->roster_next_pending_request,
        ];
    }
}
