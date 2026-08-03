<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\VacationBalanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests de los 4 conceptos de vacaciones (VacationBalanceService::getBalance())
 * según SPEC-VACACIONES v2 (definiciones del cliente, confirmadas 31/07/2026),
 * que SUPERSEDE el mapeo original de la Fase 2:
 *
 *   Pendientes = initial + accrued (SIN restar lo tomado)
 *   Gozadas    = taken
 *   Truncas    = perMonth × mesesCompletos + (perMonth / 30) × díasSueltos,
 *                del año laboral EN CURSO (tope 11 meses, 29 días) —
 *                dozavos y treintavos, D.S. 012-92-TR art. 22
 *   Saldo      = Pendientes + Truncas − Gozadas
 */
class VacationBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private VacationBalanceService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(VacationBalanceService::class);
        $this->tenant = Tenant::factory()->create(['status' => 'active', 'labor_regime' => 'general']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithHireDate(?Carbon $hireDate, Tenant $tenant, float $initial = 0): User
    {
        $user = User::factory()->create(['status' => 'active']);

        $user->tenants()->attach($tenant->id, [
            'is_primary' => true,
            'hire_date' => $hireDate?->format('Y-m-d'),
            'vacation_balance_initial' => $initial,
        ]);

        return $user;
    }

    public function test_pending_does_not_subtract_taken(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // 2 años completos de servicio -> accrued = 2 * 30 = 60.
        $hireDate = $now->copy()->subYears(2);
        $user = $this->userWithHireDate($hireDate, $this->tenant, initial: 10);

        // 7 días ya gozados: NO deben afectar a "pendientes".
        VacationRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 7,
        ]);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(2, $balance['years_of_service']);
        $this->assertSame(60.0, $balance['accrued']);
        $this->assertSame(7.0, $balance['taken']);
        // Pendientes = initial + accrued = 10 + 60 = 70, SIN restar los 7
        // gozados (el error de la Fase 2 usaba `available` = 70 - 7 = 63).
        $this->assertSame(70.0, $balance['pending']);
    }

    public function test_truncated_is_zero_right_after_anniversary(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // hire_date con el mismo mes/día que "hoy" -> el aniversario se
        // cumple justo hoy: 0 meses completos del nuevo período.
        $hireDate = $now->copy()->subYears(3);
        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(3, $balance['years_of_service']);
        $this->assertSame(0, $balance['months_completed']);
        $this->assertSame(0.0, $balance['truncated']);
    }

    public function test_truncated_at_six_months(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // Último aniversario cumplido: exactamente 6 meses antes de "hoy".
        $lastAnniversary = $now->copy()->subMonths(6); // 2026-01-31
        $hireDate = $lastAnniversary->copy()->subYears(5); // 2021-01-31

        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(5, $balance['years_of_service']);
        $this->assertSame(6, $balance['months_completed']);
        // perMonth (general) = 30 / 12 = 2.5 -> 2.5 * 6 = 15.0 exacto.
        $this->assertSame(15.0, $balance['truncated']);
        $this->assertSame('2026-01-31', $balance['current_period_start']);
        $this->assertSame('2027-01-31', $balance['current_period_end']);
    }

    public function test_truncated_at_eleven_months_and_never_exceeds_days_per_year(): void
    {
        // Víspera del aniversario número 12: el último aniversario cumplido
        // fue hace 11 meses exactos.
        Carbon::setTestNow(Carbon::parse('2026-12-31')->startOfDay());

        $lastAnniversary = Carbon::parse('2026-01-31');
        $hireDate = $lastAnniversary->copy()->subYears(4);

        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(11, $balance['months_completed']);
        // 2.5 * 11 = 27.5: nunca llega a los 30 (days_per_year) porque el
        // mes 12 ya es un aniversario nuevo (se resuelve en `accrued`, no en
        // `truncated`).
        $this->assertSame(27.5, $balance['truncated']);
        $this->assertLessThan(30.0, $balance['truncated']);
    }

    public function test_truncated_caps_at_eleven_months_and_twentynine_days(): void
    {
        // Un día antes del aniversario: 11 meses y 30 días transcurridos.
        // months_completed debe seguir en 11 (tope) y days_completed en 29
        // (tope), no fraccionar de más.
        Carbon::setTestNow(Carbon::parse('2027-01-30')->startOfDay());

        $hireDate = Carbon::parse('2021-01-31');
        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(11, $balance['months_completed']);
        // 2.5*11 + 29*(2.5/30) = 27.5 + 2.4166... = 29.92: nunca llega a los
        // 30 (days_per_year) aunque tope-e en 11 meses y 29 días sueltos.
        $this->assertSame(29.92, $balance['truncated']);
        $this->assertLessThan(30.0, $balance['truncated']);
    }

    public function test_truncated_caps_at_eleven_months_and_twentynine_days_mype(): void
    {
        // Mismo escenario de tope, en régimen MYPE (perMonth = 1.25).
        $mypeTenant = Tenant::factory()->create(['status' => 'active', 'labor_regime' => 'micro']);
        Carbon::setTestNow(Carbon::parse('2027-01-30')->startOfDay());

        $hireDate = Carbon::parse('2021-01-31');
        $user = $this->userWithHireDate($hireDate, $mypeTenant);

        $balance = $this->service->getBalance($user, $mypeTenant->id);

        $this->assertSame(11, $balance['months_completed']);
        // 1.25*11 + 29*(1.25/30) = 13.75 + 1.2083... = 14.96: nunca llega a
        // los 15 (days_per_year MYPE).
        $this->assertSame(14.96, $balance['truncated']);
        $this->assertLessThan(15.0, $balance['truncated']);
    }

    public function test_exact_anniversary_does_not_double_count(): void
    {
        // El día exacto del aniversario: el año recién cumplido debe sumar a
        // `accrued` (no quedarse a medias en `truncated`), y el nuevo
        // período en curso arranca en 0 meses / 0 truncas.
        $hireDate = Carbon::parse('2021-01-31');
        Carbon::setTestNow(Carbon::parse('2026-01-31')->startOfDay()); // 5º aniversario exacto

        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(5, $balance['years_of_service']);
        $this->assertSame(150.0, $balance['accrued']); // 5 * 30, ya incluye el año recién cumplido
        $this->assertSame(0, $balance['months_completed']);
        $this->assertSame(0.0, $balance['truncated']);
        $this->assertSame('2026-01-31', $balance['current_period_start']);
    }

    /**
     * Guard defensivo: un hire_date futuro (dato mal cargado) no debe
     * producir años de servicio ni accrued negativos. diffInYears() es
     * firmado en esta versión de Carbon, así que sin el max(0, ...) un
     * hire_date en 2028 con "hoy" en 2026 daría years_of_service = -2 y
     * accrued negativo.
     */
    public function test_future_hire_date_does_not_produce_negative_accrued(): void
    {
        $now = Carbon::parse('2026-08-03')->startOfDay();
        Carbon::setTestNow($now);

        $futureHireDate = $now->copy()->addYears(2);
        $user = $this->userWithHireDate($futureHireDate, $this->tenant, initial: 10);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(0, $balance['years_of_service']);
        $this->assertSame(0.0, $balance['accrued']);
        $this->assertSame(0, $balance['months_completed']);
        $this->assertSame(0.0, $balance['truncated']);
        // Pendientes = initial + accrued = 10 + 0, nunca negativo.
        $this->assertSame(10.0, $balance['pending']);
    }

    /**
     * Mismo guard, vía en lote (B3): getBalancesForUsers() no debe romperse
     * ni producir cifras negativas para un usuario con hire_date futuro.
     */
    public function test_get_balances_for_users_guards_future_hire_date(): void
    {
        $now = Carbon::parse('2026-08-03')->startOfDay();
        Carbon::setTestNow($now);

        $futureHireDate = $now->copy()->addYears(3);
        $user = $this->userWithHireDate($futureHireDate, $this->tenant, initial: 5);

        $balances = $this->service->getBalancesForUsers(collect([$user]), $this->tenant->id);

        $this->assertSame(0, $balances[$user->id]['years_of_service']);
        $this->assertSame(0.0, $balances[$user->id]['accrued']);
        $this->assertSame(5.0, $balances[$user->id]['pending']);
    }

    public function test_balance_without_hire_date_does_not_break(): void
    {
        $user = $this->userWithHireDate(null, $this->tenant, initial: 12);

        VacationRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 4,
        ]);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(0.0, $balance['accrued']);
        $this->assertSame(0, $balance['years_of_service']);
        $this->assertSame(0, $balance['months_completed']);
        $this->assertSame(0.0, $balance['truncated']);
        $this->assertNull($balance['current_period_start']);
        $this->assertNull($balance['current_period_end']);
        // pendientes = initial (12), gozadas = 4, saldo = initial - taken.
        $this->assertSame(12.0, $balance['pending']);
        $this->assertSame(4.0, $balance['taken']);
        $this->assertSame(8.0, $balance['balance']);
    }

    public function test_mype_regime_per_month_is_one_point_two_five(): void
    {
        $mypeTenant = Tenant::factory()->create(['status' => 'active', 'labor_regime' => 'micro']);

        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // 4 meses completos desde el último aniversario.
        $lastAnniversary = $now->copy()->subMonths(4);
        $hireDate = $lastAnniversary->copy()->subYears(2);

        $user = $this->userWithHireDate($hireDate, $mypeTenant);

        $balance = $this->service->getBalance($user, $mypeTenant->id);

        $this->assertSame(15, $balance['days_per_year']);
        $this->assertSame(4, $balance['months_completed']);
        // perMonth (MYPE) = 15 / 12 = 1.25 -> 1.25 * 4 = 5.0.
        $this->assertSame(5.0, $balance['truncated']);
    }

    public function test_mype_days_sueltos_use_one_point_two_five_fraction(): void
    {
        // Verifica que la fracción diaria en MYPE es (1.25 / 30), no
        // (2.5 / 30): 3 meses y 15 días sueltos.
        $mypeTenant = Tenant::factory()->create(['status' => 'active', 'labor_regime' => 'micro']);

        $lastAnniversary = Carbon::parse('2026-04-01');
        $now = $lastAnniversary->copy()->addMonths(3)->addDays(15); // 2026-07-16
        Carbon::setTestNow($now->copy()->startOfDay());

        $hireDate = $lastAnniversary->copy()->subYears(2);
        $user = $this->userWithHireDate($hireDate, $mypeTenant);

        $balance = $this->service->getBalance($user, $mypeTenant->id);

        $this->assertSame(3, $balance['months_completed']);
        // 1.25*3 + 15*(1.25/30) = 3.75 + 0.625 = 4.375 -> redondeado 4.38.
        $this->assertSame(4.38, $balance['truncated']);
    }

    public function test_truncated_days_static_matches_old_rule_when_days_sueltos_is_zero(): void
    {
        // Requisito duro: en cada mes cumplido exacto (díasSueltos = 0), el
        // nuevo cálculo (dozavos y treintavos) debe coincidir EXACTAMENTE
        // con la regla anterior (perMonth x mesesCompletos) — es la garantía
        // de que se sigue cumpliendo el "2.5 x mes" de la tabla del cliente.
        $this->assertSame(17.5, VacationBalanceService::truncatedDays(30, 7, 0)); // 2.5 * 7
        $this->assertSame(6.25, VacationBalanceService::truncatedDays(15, 5, 0)); // 1.25 * 5
        $this->assertSame(0.0, VacationBalanceService::truncatedDays(30, 0, 0));
    }

    public function test_truncated_five_months_twentyfour_days_matches_real_employee_case(): void
    {
        // Caso real: empleado con ingreso 10/02/2026, medido el 03/08/2026 ->
        // 5 meses y 24 días desde el último aniversario (el propio hire_date,
        // año 0 de servicio). Ejemplo fijado con el cliente.
        Carbon::setTestNow(Carbon::parse('2026-08-03')->startOfDay());

        $hireDate = Carbon::parse('2026-02-10');
        $user = $this->userWithHireDate($hireDate, $this->tenant);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(0, $balance['years_of_service']);
        $this->assertSame(5, $balance['months_completed']);
        // 2.5*5 + 24*(2.5/30) = 12.5 + 2.0 = 14.5.
        $this->assertSame(14.5, $balance['truncated']);
    }

    public function test_balance_equals_pending_plus_truncated_minus_taken_with_concrete_numbers(): void
    {
        // Día 15 (en vez de 31) para no toparse con el desborde de Carbon al
        // restar meses a partir de un día-31 en un mes más corto.
        $now = Carbon::parse('2026-07-15')->startOfDay();
        Carbon::setTestNow($now);

        // 1 año completo de servicio (accrued = 30) + 3 meses del período en
        // curso (truncas = 2.5 * 3 = 7.5).
        $lastAnniversary = $now->copy()->subMonths(3);
        $hireDate = $lastAnniversary->copy()->subYears(1);

        $user = $this->userWithHireDate($hireDate, $this->tenant, initial: 10);

        VacationRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 12,
        ]);

        $balance = $this->service->getBalance($user, $this->tenant->id);

        $this->assertSame(40.0, $balance['pending']); // 10 + 30
        $this->assertSame(7.5, $balance['truncated']); // 2.5 * 3
        $this->assertSame(12.0, $balance['taken']);
        // Saldo = 40 + 7.5 - 12 = 35.5
        $this->assertSame(35.5, $balance['balance']);
        // Atajo: available (= pendientes - gozadas) + truncas = saldo.
        $this->assertSame($balance['available'] + $balance['truncated'], $balance['balance']);
    }

    /**
     * Vía en lote (B3): getBalancesForUsers() no debe ejecutar N+1 sobre
     * user_tenants/vacation_requests. Con 20 usuarios el número de queries
     * debe ser el mismo que con 1 (2 queries: UserTenant y VacationRequest
     * agregados), no crecer con N.
     */
    public function test_get_balances_for_users_does_not_n_plus_one(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        $users = collect();
        for ($i = 0; $i < 20; $i++) {
            $hireDate = $i % 2 === 0 ? $now->copy()->subYears(2)->subMonths(3) : null;
            $users->push($this->userWithHireDate($hireDate, $this->tenant, initial: 5));
        }

        // Algunos con días tomados, para ejercitar el join agregado.
        VacationRequest::factory()->approved()->create([
            'user_id' => $users->first()->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 3,
        ]);

        DB::enableQueryLog();
        $balances20 = $this->service->getBalancesForUsers($users, $this->tenant->id);
        $queryCount20 = count(DB::getQueryLog());
        DB::flushQueryLog();

        $balances1 = $this->service->getBalancesForUsers($users->take(1), $this->tenant->id);
        $queryCount1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(20, $balances20);
        $this->assertCount(1, $balances1);
        // Mismo número de queries con 1 o con 20 usuarios: no hay N+1.
        $this->assertSame($queryCount1, $queryCount20);
        // Cota dura: no más de 3 queries (Tenant::findOrFail + los 2 whereIn
        // agregados), sin importar N.
        $this->assertLessThanOrEqual(3, $queryCount20);
    }

    public function test_get_balances_for_users_matches_get_balance_per_user(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        $userA = $this->userWithHireDate($now->copy()->subYears(2), $this->tenant, initial: 10);
        VacationRequest::factory()->approved()->create([
            'user_id' => $userA->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 7,
        ]);

        $userB = $this->userWithHireDate(null, $this->tenant, initial: 12);

        $batch = $this->service->getBalancesForUsers(collect([$userA, $userB]), $this->tenant->id);

        $expectedA = $this->service->getBalance($userA, $this->tenant->id);
        $expectedB = $this->service->getBalance($userB, $this->tenant->id);

        $this->assertSame($expectedA['pending'], $batch[$userA->id]['pending']);
        $this->assertSame($expectedA['taken'], $batch[$userA->id]['taken']);
        $this->assertSame($expectedA['truncated'], $batch[$userA->id]['truncated']);
        $this->assertSame($expectedA['balance'], $batch[$userA->id]['balance']);

        // Usuario sin hire_date: no debe romper el cálculo en lote.
        $this->assertSame($expectedB['pending'], $batch[$userB->id]['pending']);
        $this->assertSame($expectedB['balance'], $batch[$userB->id]['balance']);
        $this->assertNull($batch[$userB->id]['hire_date']);
    }

    public function test_get_balances_for_users_differs_per_tenant_for_same_user(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        $tenantB = Tenant::factory()->create(['status' => 'active', 'labor_regime' => 'general']);

        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($this->tenant->id, [
            'is_primary' => true,
            'hire_date' => $now->copy()->subYears(3)->format('Y-m-d'),
            'vacation_balance_initial' => 5,
        ]);
        $user->tenants()->attach($tenantB->id, [
            'is_primary' => false,
            'hire_date' => $now->copy()->subYears(1)->format('Y-m-d'),
            'vacation_balance_initial' => 0,
        ]);

        $balanceInA = $this->service->getBalancesForUsers(collect([$user]), $this->tenant->id);
        $balanceInB = $this->service->getBalancesForUsers(collect([$user]), $tenantB->id);

        // 3 años vs 1 año de servicio + distinto initial -> pendientes distintos.
        $this->assertSame(95.0, $balanceInA[$user->id]['pending']); // 5 + 3*30
        $this->assertSame(30.0, $balanceInB[$user->id]['pending']); // 0 + 1*30
        $this->assertNotEquals($balanceInA[$user->id]['pending'], $balanceInB[$user->id]['pending']);
    }

    public function test_get_balances_for_users_empty_collection_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->getBalancesForUsers(collect(), $this->tenant->id));
    }
}
