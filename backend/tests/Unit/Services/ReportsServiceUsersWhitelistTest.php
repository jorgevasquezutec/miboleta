<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-08]: getUsersReportData() exporta EMPLEADOS
 * (User::ORG_EMPLOYEE_ROLES = admin/client/aprobador) POR ROL EN CADA
 * EMPRESA, nunca cuentas de aplicación (root, admin_tenant). Cubre:
 *   - whitelist por tenant concreto (admin_tenant puro no sale).
 *   - export global (root, sin tenant_id): whitelist + cinturón anti-root,
 *     SIN excluir admin_tenant (el dual A-admin_tenant/B-client debe seguir
 *     apareciendo por su rol de empleado en B).
 *   - columna 'Rol': la de mayor prioridad EN la empresa exportada (o entre
 *     todas, en el export global).
 *   - exclusión del actor no-root (no se ve a sí mismo ni a admin_tenant).
 */
class ReportsServiceUsersWhitelistTest extends TestCase
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

    public function test_tenant_export_excludes_pure_admin_tenant_and_root(): void
    {
        $adminTenantOnly = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $root = User::factory()->root()->create(['status' => 'active']);
        $root->tenants()->attach($this->tenantA->id, ['is_primary' => true]);

        $client = User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $rows = $this->service->getUsersReportData(['tenant_id' => $this->tenantA->id]);
        $ids = $rows->pluck('ID');

        $this->assertTrue($ids->contains($client->id));
        $this->assertFalse($ids->contains($adminTenantOnly->id), 'admin_tenant puro no debe salir en el export de empleados');
        $this->assertFalse($ids->contains($root->id), 'root no debe salir en el export de empleados');
    }

    public function test_dual_admin_tenant_and_client_appears_in_global_export_and_in_export_of_its_employee_tenant(): void
    {
        // Dual: admin_tenant en A (donde NO es empleado), client en B (donde
        // SÍ es empleado).
        $dual = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $globalRows = $this->service->getUsersReportData([]);
        $rowsInA = $this->service->getUsersReportData(['tenant_id' => $this->tenantA->id]);
        $rowsInB = $this->service->getUsersReportData(['tenant_id' => $this->tenantB->id]);

        $this->assertTrue(
            $globalRows->pluck('ID')->contains($dual->id),
            'El dual debe aparecer en el export global por su rol de empleado en B'
        );
        $this->assertFalse($rowsInA->pluck('ID')->contains($dual->id), 'En A solo es admin_tenant: no debe salir ahí');
        $this->assertTrue($rowsInB->pluck('ID')->contains($dual->id), 'En B es client: debe salir ahí');

        $globalRow = $globalRows->firstWhere('ID', $dual->id);
        $this->assertSame('Empleado', $globalRow['Rol'], 'Su único rol de empleado (client) es el que debe mostrarse, no admin_tenant');
    }

    public function test_role_column_reflects_the_exported_tenant_not_another_one(): void
    {
        // admin en A, client en B: la columna Rol debe cambiar según la
        // empresa exportada (antes usaba getCurrentRole() sin tenant, que
        // podía devolver el rol de OTRA empresa).
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $rowInA = $this->service->getUsersReportData(['tenant_id' => $this->tenantA->id])->firstWhere('ID', $user->id);
        $rowInB = $this->service->getUsersReportData(['tenant_id' => $this->tenantB->id])->firstWhere('ID', $user->id);

        $this->assertSame('Admin Empleados', $rowInA['Rol']);
        $this->assertSame('Empleado', $rowInB['Rol']);
    }

    public function test_acting_non_root_user_does_not_see_itself_nor_admin_tenant(): void
    {
        $actor = User::factory()
            ->withTenantRole($this->tenantA, 'admin', true)
            ->create(['status' => 'active']);

        $adminTenant = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant')
            ->create(['status' => 'active']);

        $client = User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $rows = $this->service->getUsersReportData([
            'tenant_id' => $this->tenantA->id,
            'acting_user' => $actor,
        ]);
        $ids = $rows->pluck('ID');

        $this->assertFalse($ids->contains($actor->id), 'El actor no-root no debe verse a sí mismo');
        $this->assertFalse($ids->contains($adminTenant->id), 'El actor no-root no debe ver admin_tenant');
        $this->assertTrue($ids->contains($client->id));
    }

    public function test_acting_root_user_is_not_excluded_from_its_own_export(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);
        $root->tenants()->attach($this->tenantA->id, ['is_primary' => true]);
        // Root también puede ser 'client' en alguna empresa (caso raro pero
        // no imposible); lo relevante es que la exclusión de actor NO aplica
        // para root.
        \App\Models\UserTenantRole::create([
            'user_id' => $root->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => \App\Models\Role::where('name', 'client')->first()->id,
        ]);

        $adminTenant = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant')
            ->create(['status' => 'active']);
        \App\Models\UserTenantRole::create([
            'user_id' => $adminTenant->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => \App\Models\Role::where('name', 'client')->first()->id,
        ]);

        $rows = $this->service->getUsersReportData([
            'tenant_id' => $this->tenantA->id,
            'acting_user' => $root,
        ]);
        $ids = $rows->pluck('ID');

        $this->assertTrue($ids->contains($root->id), 'Root no se excluye a sí mismo');
        // Regla "admin_tenant domina": el dual admin_tenant+client de ESTA
        // empresa no es empleado aquí, así que tampoco sale en el export de
        // empleados de root (root lo ve en el reporte de Cuentas de
        // Aplicación, no en este).
        $this->assertFalse($ids->contains($adminTenant->id), 'El dual admin_tenant+client no es empleado de esta empresa');
    }
}
