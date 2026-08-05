<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-08]: el export de Organizaciones
 * (ReportsService::getTenantReportData(), consumido por
 * ReportsController::exportTenants, solo-root) separa 'empleados'
 * (User::ORG_EMPLOYEE_ROLES, regla "admin_tenant domina") de
 * 'cuentas_aplicacion' (admin_tenant de la empresa) — antes una sola columna
 * withCount('users') mezclaba ambos.
 */
class ReportsServiceTenantReportTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->service = app(ReportsService::class);
    }

    public function test_empleados_column_excludes_admin_tenant_and_counts_it_as_app_account(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Empresa Test', 'status' => 'active']);

        User::factory()->withTenantRole($tenant, 'admin_tenant', true)->create(['status' => 'active']);
        User::factory()->withTenantRole($tenant, 'client', true)->create(['status' => 'active']);
        User::factory()->withTenantRole($tenant, 'admin', true)->create(['status' => 'active']);

        $rows = $this->service->getTenantReportData([]);
        $row = $rows->firstWhere('id', $tenant->id);

        $this->assertNotNull($row);
        $this->assertSame(2, $row['empleados'], 'admin_tenant no debe sumar en la columna empleados');
        $this->assertSame(1, $row['cuentas_aplicacion'], 'admin_tenant va en su propia columna');
    }

    public function test_empleados_column_counts_dual_employee_role_user_once(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Empresa Dual', 'status' => 'active']);

        $user = User::factory()->withTenantRole($tenant, 'admin', true)->create(['status' => 'active']);
        \App\Models\UserTenantRole::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role_id' => \App\Models\Role::where('name', 'client')->first()->id,
        ]);

        $rows = $this->service->getTenantReportData([]);
        $row = $rows->firstWhere('id', $tenant->id);

        $this->assertSame(1, $row['empleados']);
        $this->assertSame(0, $row['cuentas_aplicacion']);
    }
}
