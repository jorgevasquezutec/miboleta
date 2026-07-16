<?php

namespace App\Services;

use App\Models\Scopes\TenantFilterScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenant;
use App\Models\VacationRequest;
use Carbon\Carbon;

/**
 * Calcula el saldo de vacaciones de un usuario en una empresa, según el
 * régimen laboral peruano (D.Leg. 713 / régimen MYPE), por años de
 * servicio (sin récord vacacional de asistencia).
 *
 * Fórmula:
 *   disponible = saldo_inicial + devengo - días_tomados
 *
 *   - saldo_inicial: user_tenants.vacation_balance_initial (sembrado en la
 *     carga masiva).
 *   - devengo: días/año (según Tenant::vacationDaysPerYear()) × años
 *     completos de servicio (aniversarios de user_tenants.hire_date
 *     cumplidos a la fecha). Sin hire_date, el devengo es 0.
 *   - días_tomados: suma de days_requested de VacationRequest en estado
 *     approved o was_taken=true, para ese (user, tenant).
 */
class VacationBalanceService
{
    /**
     * Obtener el saldo de vacaciones de un usuario en una empresa.
     *
     * @return array{
     *     tenant_id:int,
     *     labor_regime:string,
     *     days_per_year:int,
     *     initial:float,
     *     accrued:float,
     *     taken:float,
     *     available:float,
     *     hire_date:?string,
     *     years_of_service:int
     * }
     */
    public function getBalance(User $user, int $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);

        $userTenant = UserTenant::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        $daysPerYear = $tenant->vacationDaysPerYear();

        /** @var Carbon|null $hireDate */
        $hireDate = $userTenant?->hire_date;
        $initial = (float) ($userTenant?->vacation_balance_initial ?? 0);

        $yearsOfService = 0;
        $accrued = 0.0;

        if ($hireDate) {
            // Años completos de servicio = número de aniversarios de
            // hire_date ya cumplidos a la fecha actual.
            $yearsOfService = (int) $hireDate->diffInYears(Carbon::now());
            $accrued = $yearsOfService * $daysPerYear;
        }

        $taken = (float) $this->takenDaysQuery($user->id, $tenantId)->sum('days_requested');

        $available = round($initial + $accrued - $taken, 2);

        return [
            'tenant_id' => $tenantId,
            'labor_regime' => $tenant->labor_regime,
            'days_per_year' => $daysPerYear,
            'initial' => round($initial, 2),
            'accrued' => round($accrued, 2),
            'taken' => round($taken, 2),
            'available' => $available,
            'hire_date' => $hireDate?->format('Y-m-d'),
            'years_of_service' => $yearsOfService,
        ];
    }

    /**
     * Obtener el supervisor/aprobador del usuario para una empresa.
     */
    public function getSupervisorForTenant(User $user, int $tenantId): ?User
    {
        return $user->getSupervisorForTenant($tenantId);
    }

    /**
     * Query de días de vacaciones ya confirmados (tomados) para ese
     * (user, tenant): status=approved o was_taken=true.
     *
     * Se ignora el global scope TenantFilterScope (pensado para filtrar
     * listados según el header X-Tenant-Ids del switcher multi-empresa) para
     * que el cálculo del saldo sea siempre correcto independientemente del
     * filtro de tenant activo en la sesión.
     */
    protected function takenDaysQuery(int $userId, int $tenantId)
    {
        return VacationRequest::withoutGlobalScope(TenantFilterScope::class)
            ->forUser($userId)
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('status', VacationRequest::STATUS_APPROVED)
                    ->orWhere('was_taken', true);
            });
    }
}
