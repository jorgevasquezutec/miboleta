<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\ReportsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B3: columnas de vacaciones (Pendientes/Gozadas/Truncas/Saldo) en el export
 * de usuarios (ReportsService::getUsersReportData(), consumido por
 * ReportsController::exportUsers). Encabezados con el vocabulario exacto del
 * cliente (mensaje del 31/07/2026, ver SPEC-VACACIONES v2): las claves del
 * array mapeado son literalmente los encabezados que GenericExport escribe
 * en el Excel (no pasa `headings` explícitos, toma array_keys() de la
 * primera fila).
 */
class ReportsServiceUsersExportTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ReportsService::class);
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

    public function test_export_includes_the_four_columns_with_client_headers(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // 2 años completos (accrued = 60) + 10 inicial = 70 pendientes.
        $user = $this->userWithHireDate($now->copy()->subYears(2), $this->tenant, initial: 10);
        VacationRequest::factory()->approved()->create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenant->id,
            'days_requested' => 7,
        ]);

        $rows = $this->service->getUsersReportData(['tenant_id' => $this->tenant->id]);
        $row = $rows->firstWhere('ID', $user->id);

        $this->assertNotNull($row);
        // Vocabulario exacto del cliente, tal cual se usa como encabezado.
        $this->assertArrayHasKey('Vacaciones Pendientes', $row);
        $this->assertArrayHasKey('Vacaciones Gozadas', $row);
        $this->assertArrayHasKey('Vacaciones Truncas', $row);
        $this->assertArrayHasKey('Saldo Vacaciones', $row);

        $this->assertSame(70.0, $row['Vacaciones Pendientes']); // 10 + 2*30
        $this->assertSame(7.0, $row['Vacaciones Gozadas']);
        $this->assertSame(0.0, $row['Vacaciones Truncas']); // justo en el aniversario
        $this->assertSame(63.0, $row['Saldo Vacaciones']); // 70 + 0 - 7
    }

    public function test_export_does_not_break_for_user_without_hire_date(): void
    {
        $user = $this->userWithHireDate(null, $this->tenant, initial: 12);

        $rows = $this->service->getUsersReportData(['tenant_id' => $this->tenant->id]);
        $row = $rows->firstWhere('ID', $user->id);

        $this->assertNotNull($row);
        $this->assertSame(12.0, $row['Vacaciones Pendientes']);
        $this->assertSame(0.0, $row['Vacaciones Gozadas']);
        $this->assertSame(0.0, $row['Vacaciones Truncas']);
        $this->assertSame(12.0, $row['Saldo Vacaciones']);
    }

    public function test_export_shows_na_when_no_single_active_tenant(): void
    {
        // Sin tenant_id (root en modo "todas las empresas"): no hay una
        // empresa activa inequívoca, así que no se calcula el saldo — "N/A",
        // nunca un total inventado sumando empresas distintas.
        $this->userWithHireDate(Carbon::parse('2020-01-01'), $this->tenant, initial: 5);

        $rows = $this->service->getUsersReportData([]);

        $this->assertGreaterThan(0, $rows->count());
        foreach ($rows as $row) {
            $this->assertSame('N/A', $row['Vacaciones Pendientes']);
            $this->assertSame('N/A', $row['Vacaciones Gozadas']);
            $this->assertSame('N/A', $row['Vacaciones Truncas']);
            $this->assertSame('N/A', $row['Saldo Vacaciones']);
        }
    }

    public function test_export_gives_different_figures_per_tenant_for_same_user(): void
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

        $rowInA = $this->service->getUsersReportData(['tenant_id' => $this->tenant->id])->firstWhere('ID', $user->id);
        $rowInB = $this->service->getUsersReportData(['tenant_id' => $tenantB->id])->firstWhere('ID', $user->id);

        $this->assertSame(95.0, $rowInA['Vacaciones Pendientes']); // 5 + 3*30
        $this->assertSame(30.0, $rowInB['Vacaciones Pendientes']); // 0 + 1*30
        $this->assertNotEquals($rowInA['Vacaciones Pendientes'], $rowInB['Vacaciones Pendientes']);
    }
}
