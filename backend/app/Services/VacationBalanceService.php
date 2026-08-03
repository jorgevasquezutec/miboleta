<?php

namespace App\Services;

use App\Models\Scopes\TenantFilterScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenant;
use App\Models\VacationRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcula los 4 conceptos de vacaciones de un usuario en una empresa, según
 * el régimen laboral peruano (D.Leg. 713 / régimen MYPE), por años de
 * servicio (sin récord vacacional de asistencia).
 *
 * Definiciones tal como las entregó el cliente el 31/07/2026 (SPEC-VACACIONES
 * v2, que SUPERSEDE el mapeo original implementado en la Fase 2 — ver delta
 * documentado ahí):
 *
 *   Vacaciones Pendientes = initial + accrued
 *     - initial: user_tenants.vacation_balance_initial (carga masiva o alta
 *       manual).
 *     - accrued: días/año (Tenant::vacationDaysPerYear()) × años completos
 *       de servicio (aniversarios de user_tenants.hire_date ya cumplidos a
 *       la fecha). Sin hire_date, accrued = 0.
 *     NO resta lo tomado (a diferencia de la Fase 2, que sí lo restaba): son
 *     los días acumulados de años YA vencidos, tomados o no.
 *
 *   Vacaciones Gozadas = taken
 *     - suma de days_requested de VacationRequest en estado approved o
 *       was_taken=true, para ese (user, tenant).
 *
 *   Vacaciones Truncas = perMonth × mesesCompletos + (perMonth / 30) × díasSueltos
 *     - perMonth = daysPerYear / 12 (2.5 general, 1.25 MYPE).
 *     - mesesCompletos: meses completos transcurridos desde el último
 *       aniversario cumplido, tope 11.
 *     - díasSueltos: días transcurridos desde la marca del último mes
 *       completo (aniversario + mesesCompletos), tope 29.
 *     Dozavos y treintavos: así liquida el récord trunco el D.S. 012-92-TR
 *       art. 22 ("tantos dozavos y treintavos ... como meses y días
 *       computables hubiere laborado"), con mes comercial de 30 días (no
 *       la longitud real del mes calendario). Reemplaza el cálculo previo
 *       de solo mesesCompletos (Fase 3), que a su vez reemplazó el de la
 *       Fase 2 (proporcional por días transcurridos / días del período).
 *       En cada mes cumplido exacto (díasSueltos = 0) el resultado es
 *       idéntico al de solo mesesCompletos — el "2.5 x mes" de la tabla
 *       del cliente —, así que los treintavos no lo contradicen: solo
 *       añaden precisión en los días intermedios.
 *
 *   Saldo Vacaciones = Pendientes + Truncas − Gozadas
 *
 * Atajo de implementación: `available` (= initial + accrued − taken =
 * Pendientes − Gozadas) se sigue calculando y exponiendo — lo sigue usando
 * ProfilePage.tsx ("días disponibles"), fuera del alcance de este cambio —
 * y el Saldo se deriva como `available + truncas` (ver computeFourFigures()).
 *
 * Aniversario exacto: `years_of_service` viene de hireDate->diffInYears(now),
 * que ya cuenta el año como completo el mismo día del aniversario. Ese día,
 * `lastAnniversary` pasa a ser "hoy", mesesCompletos = 0 y el devengo del año
 * recién cerrado ya quedó sumado en `accrued` — sin doble conteo.
 */
class VacationBalanceService
{
    /**
     * Vía en lote (B3 — columnas de vacaciones en la tabla de usuarios y el
     * export Excel). Para N usuarios, getBalance() en un loop sería N+1 (2
     * queries por usuario: UserTenant::where(...)->first() y
     * takenDaysQuery()->sum()). Esta versión hace exactamente 2 queries en
     * total, sin importar N:
     *
     *   1. UserTenant de TODOS los $users para $tenantId, en una sola query
     *      (whereIn('user_id', ...)) — initial + hire_date.
     *   2. `taken` agregado de TODOS los $users para $tenantId, en una sola
     *      query (whereIn + groupBy('user_id') + sum(days_requested)).
     *
     * Por cada usuario, years_of_service/mesesCompletos/díasSueltos se
     * resuelven en PHP con Carbon (sin ir a la BD) reutilizando
     * monthsCompletedSince() + daysCompletedSince() + truncatedDays() +
     * computeFourFigures() — el mismo cálculo puro que usa getBalance(), sin
     * repetir la fórmula.
     *
     * Igual que takenDaysQuery(), ignora a propósito TenantFilterScope: el
     * saldo debe ser correcto independientemente del filtro de tenant activo
     * en la sesión.
     *
     * @param Collection<int, User> $users
     * @return array<int, array{
     *     tenant_id:int,
     *     days_per_year:int,
     *     initial:float,
     *     accrued:float,
     *     taken:float,
     *     available:float,
     *     pending:float,
     *     truncated:float,
     *     balance:float,
     *     hire_date:?string,
     *     years_of_service:int,
     *     months_completed:int
     * }> indexado por user_id
     */
    public function getBalancesForUsers(Collection $users, int $tenantId): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $tenant = Tenant::findOrFail($tenantId);
        $daysPerYear = $tenant->vacationDaysPerYear();
        $userIds = $users->pluck('id')->all();

        // 1 query: initial + hire_date de todos los usuarios en este tenant.
        $userTenants = UserTenant::where('tenant_id', $tenantId)
            ->whereIn('user_id', $userIds)
            ->get(['user_id', 'vacation_balance_initial', 'hire_date'])
            ->keyBy('user_id');

        // 1 query agregada: taken de todos los usuarios en este tenant.
        $takenByUser = VacationRequest::withoutGlobalScope(TenantFilterScope::class)
            ->whereIn('user_id', $userIds)
            ->where('tenant_id', $tenantId)
            ->where(function ($query) {
                $query->where('status', VacationRequest::STATUS_APPROVED)
                    ->orWhere('was_taken', true);
            })
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(days_requested) as taken')
            ->pluck('taken', 'user_id');

        $now = Carbon::now();
        $results = [];

        foreach ($userIds as $userId) {
            $userTenant = $userTenants->get($userId);

            /** @var Carbon|null $hireDate */
            $hireDate = $userTenant?->hire_date;
            $initial = (float) ($userTenant?->vacation_balance_initial ?? 0);
            $taken = (float) ($takenByUser[$userId] ?? 0);

            $yearsOfService = 0;
            $accrued = 0.0;
            $monthsCompleted = 0;
            $truncated = 0.0;

            if ($hireDate) {
                // Guard: diffInYears() es firmado (Carbon 3), así que un
                // hire_date futuro (dato mal cargado) daría un valor
                // negativo aquí y, sin el max(0, ...), un accrued negativo.
                // Mismo guard que ya tenían monthsCompletedSince()/
                // daysCompletedSince() para el caso análogo.
                $yearsOfService = max(0, (int) $hireDate->diffInYears($now));
                $accrued = $yearsOfService * $daysPerYear;

                $lastAnniversary = $hireDate->copy()->addYears($yearsOfService);

                $monthsCompleted = self::monthsCompletedSince($lastAnniversary, $now);
                $daysCompleted = self::daysCompletedSince($lastAnniversary, $monthsCompleted, $now);
                $truncated = self::truncatedDays($daysPerYear, $monthsCompleted, $daysCompleted);
            }

            $figures = self::computeFourFigures($initial, $accrued, $taken, $truncated);

            $results[$userId] = [
                'tenant_id' => $tenantId,
                'days_per_year' => $daysPerYear,
                'initial' => round($initial, 2),
                'accrued' => round($accrued, 2),
                'taken' => $figures['taken'],
                'available' => $figures['available'],
                'pending' => $figures['pending'],
                'truncated' => $figures['truncated'],
                'balance' => $figures['balance'],
                'hire_date' => $hireDate?->format('Y-m-d'),
                'years_of_service' => $yearsOfService,
                'months_completed' => $monthsCompleted,
            ];
        }

        return $results;
    }

    /**
     * Obtener las 4 cifras de vacaciones (+ metadatos) de un usuario en una
     * empresa.
     *
     * @return array{
     *     tenant_id:int,
     *     labor_regime:string,
     *     days_per_year:int,
     *     initial:float,
     *     accrued:float,
     *     taken:float,
     *     available:float,
     *     pending:float,
     *     truncated:float,
     *     balance:float,
     *     hire_date:?string,
     *     years_of_service:int,
     *     months_completed:int,
     *     current_period_start:?string,
     *     current_period_end:?string
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
        $monthsCompleted = 0;
        $truncated = 0.0;
        $currentPeriodStart = null;
        $currentPeriodEnd = null;

        if ($hireDate) {
            // Años completos de servicio = número de aniversarios de
            // hire_date ya cumplidos a la fecha actual.
            //
            // Guard: diffInYears() es firmado (Carbon 3), así que un
            // hire_date futuro (dato mal cargado) daría un valor negativo
            // aquí y, sin el max(0, ...), un accrued negativo. Mismo guard
            // que ya tenían monthsCompletedSince()/daysCompletedSince() para
            // el caso análogo.
            $yearsOfService = max(0, (int) $hireDate->diffInYears(Carbon::now()));
            $accrued = $yearsOfService * $daysPerYear;

            $lastAnniversary = $hireDate->copy()->addYears($yearsOfService);
            $nextAnniversary = $lastAnniversary->copy()->addYear();

            $monthsCompleted = self::monthsCompletedSince($lastAnniversary, Carbon::now());
            $daysCompleted = self::daysCompletedSince($lastAnniversary, $monthsCompleted, Carbon::now());
            $truncated = self::truncatedDays($daysPerYear, $monthsCompleted, $daysCompleted);

            $currentPeriodStart = $lastAnniversary->format('Y-m-d');
            $currentPeriodEnd = $nextAnniversary->format('Y-m-d');
        }

        $taken = (float) $this->takenDaysQuery($user->id, $tenantId)->sum('days_requested');

        $figures = self::computeFourFigures($initial, $accrued, $taken, $truncated);

        return [
            'tenant_id' => $tenantId,
            'labor_regime' => $tenant->labor_regime,
            'days_per_year' => $daysPerYear,
            'initial' => round($initial, 2),
            'accrued' => round($accrued, 2),
            'taken' => $figures['taken'],
            'available' => $figures['available'],
            'pending' => $figures['pending'],
            'truncated' => $figures['truncated'],
            'balance' => $figures['balance'],
            'hire_date' => $hireDate?->format('Y-m-d'),
            'years_of_service' => $yearsOfService,
            'months_completed' => $monthsCompleted,
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
        ];
    }

    /**
     * Cálculo puro de los 4 conceptos del cliente, dados initial/accrued/
     * taken/truncated ya resueltos. No accede a BD ni a Carbon::now(): es la
     * pieza reutilizable tanto para un usuario (getBalance) como, en el
     * futuro, para un lote de usuarios (B3) sin repetir la fórmula.
     *
     * @return array{pending:float, taken:float, truncated:float, available:float, balance:float}
     */
    public static function computeFourFigures(float $initial, float $accrued, float $taken, float $truncated): array
    {
        $pending = round($initial + $accrued, 2);
        $taken = round($taken, 2);
        $truncated = round($truncated, 2);

        // Atajo (ver docblock de clase): available = pendientes - gozadas;
        // saldo = available + truncas.
        $available = round($pending - $taken, 2);
        $balance = round($available + $truncated, 2);

        return [
            'pending' => $pending,
            'taken' => $taken,
            'truncated' => $truncated,
            'available' => $available,
            'balance' => $balance,
        ];
    }

    /**
     * Meses COMPLETOS transcurridos desde el último aniversario cumplido,
     * tope 11: el mes 12 ya es un aniversario nuevo (yearsOfService se
     * incrementa y accrued lo absorbe en getBalance(), ver docblock de
     * clase sobre el aniversario exacto). No depende de ninguna query:
     * recibe las fechas ya resueltas, así que es reutilizable para el
     * cálculo en lote de B3 sin volver a tocar la BD por usuario.
     */
    public static function monthsCompletedSince(Carbon $lastAnniversary, Carbon $now): int
    {
        return min(11, max(0, (int) $lastAnniversary->diffInMonths($now)));
    }

    /**
     * Días sueltos (treintavos) transcurridos desde la marca del último mes
     * completo (aniversario + mesesCompletos), tope 29: el día 30 ya
     * cerraría un mes completo más, que monthsCompletedSince() tope-a en 11
     * — por eso el tope de días es 29, no 30. Pura: no accede a BD ni a
     * Carbon::now(), reutilizable para B3.
     */
    public static function daysCompletedSince(Carbon $lastAnniversary, int $monthsCompleted, Carbon $now): int
    {
        $lastMonthMark = $lastAnniversary->copy()->addMonths(min(11, max(0, $monthsCompleted)));

        return min(29, max(0, (int) $lastMonthMark->diffInDays($now)));
    }

    /**
     * Vacaciones Truncas del año laboral en curso: dozavos y treintavos
     * (D.S. 012-92-TR art. 22) — perMonth × mesesCompletos + (perMonth / 30)
     * × díasSueltos, con perMonth = daysPerYear / 12 (2.5 general, 1.25
     * MYPE) y el treintavo usando el mes comercial de 30 días. Con
     * díasSueltos = 0 coincide exactamente con la regla previa (solo
     * mesesCompletos). Pura: no accede a BD ni a Carbon::now(), reutilizable
     * para B3.
     */
    public static function truncatedDays(int $daysPerYear, int $monthsCompleted, int $daysCompleted): float
    {
        $perMonth = $daysPerYear / 12;
        $monthsCompleted = min(11, max(0, $monthsCompleted));
        $daysCompleted = min(29, max(0, $daysCompleted));

        return round($perMonth * $monthsCompleted + ($perMonth / 30) * $daysCompleted, 2);
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
