<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-08]: ReportsService::getAppAccountsReportData() exporta
 * las cuentas de APLICACIÓN (root global + admin_tenant por empresa) — el
 * complemento de getUsersReportData(), que exporta empleados. Consumido por
 * ReportsController::exportAppAccounts.
 */
class ReportsServiceAppAccountsTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->service = app(ReportsService::class);
        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B', 'status' => 'active']);
    }

    public function test_root_viewer_sees_all_roots_and_all_admin_tenants(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);
        $adminTenantA = User::factory()->withTenantRole($this->tenantA, 'admin_tenant', true)->create(['status' => 'active']);
        $adminTenantB = User::factory()->withTenantRole($this->tenantB, 'admin_tenant', true)->create(['status' => 'active']);
        // Ruido: un empleado normal jamás debe aparecer aquí.
        $employee = User::factory()->withTenantRole($this->tenantA, 'client', true)->create(['status' => 'active']);

        $rows = $this->service->getAppAccountsReportData(null);
        $ids = $rows->pluck('ID');

        $this->assertTrue($ids->contains($root->id));
        $this->assertTrue($ids->contains($adminTenantA->id));
        $this->assertTrue($ids->contains($adminTenantB->id));
        $this->assertFalse($ids->contains($employee->id));

        $rootRow = $rows->firstWhere('ID', $root->id);
        $this->assertSame('Administrador Plataforma', $rootRow['Tipo de cuenta']);
        $this->assertSame('Todas (plataforma)', $rootRow['Empresas que administra']);

        $adminARow = $rows->firstWhere('ID', $adminTenantA->id);
        $this->assertSame('Admin Clientes', $adminARow['Tipo de cuenta']);
        $this->assertSame('Empresa A', $adminARow['Empresas que administra']);
    }

    public function test_tenant_ids_scope_limits_admin_tenants_and_their_company_column(): void
    {
        User::factory()->root()->create(['status' => 'active']); // siempre presente
        $adminOfA = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin_tenant') // administra 2 empresas
            ->create(['status' => 'active']);
        $adminOfB = User::factory()->withTenantRole($this->tenantB, 'admin_tenant', true)->create(['status' => 'active']);

        // Viewer (no-root) que administra SOLO la empresa A.
        $rows = $this->service->getAppAccountsReportData([$this->tenantA->id]);
        $ids = $rows->pluck('ID');

        $this->assertTrue($ids->contains($adminOfA->id), 'Administra A: debe verse');
        $this->assertFalse($ids->contains($adminOfB->id), 'Solo administra B, fuera del scope del viewer: no debe verse');

        $rowOfA = $rows->firstWhere('ID', $adminOfA->id);
        $this->assertSame(
            'Empresa A',
            $rowOfA['Empresas que administra'],
            'La columna Empresas no debe filtrar B, que es ajena al viewer'
        );
    }

    public function test_roots_are_present_by_default_even_with_tenant_ids_scope(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        $rows = $this->service->getAppAccountsReportData([$this->tenantA->id]);

        $this->assertTrue($rows->pluck('ID')->contains($root->id));
    }

    public function test_roots_are_excluded_when_include_roots_is_false(): void
    {
        // Viewer admin_tenant: exporta las cuentas de su empresa activa; las
        // cuentas root son de plataforma y quedan fuera.
        $root = User::factory()->root()->create(['status' => 'active']);
        $adminOfA = User::factory()->withTenantRole($this->tenantA, 'admin_tenant', true)->create(['status' => 'active']);

        $rows = $this->service->getAppAccountsReportData([$this->tenantA->id], includeRoots: false);
        $ids = $rows->pluck('ID');

        $this->assertFalse($ids->contains($root->id));
        $this->assertTrue($ids->contains($adminOfA->id));
    }

    public function test_dual_root_and_admin_tenant_appears_once_as_root(): void
    {
        $dual = User::factory()->root()->withTenantRole($this->tenantA, 'admin_tenant', true)->create(['status' => 'active']);

        $rows = $this->service->getAppAccountsReportData(null);
        $matches = $rows->where('ID', $dual->id);

        $this->assertCount(1, $matches, 'Debe salir una sola vez, no duplicado');
        $this->assertSame('Administrador Plataforma', $matches->first()['Tipo de cuenta']);
    }
}
