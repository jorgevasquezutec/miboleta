<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para ReportsService::getUserStats().
 *
 * Bugs originales:
 *   (a) reutilizaba un builder entre métricas: los where se acumulaban sobre
 *       la misma instancia y 'inactive' quedaba insatisfacible (siempre 0).
 *   (b) by_role unía contra la tabla global user_roles (la UNIÓN de roles del
 *       usuario en TODAS sus empresas) en vez de user_tenant_roles, así que un
 *       usuario multi-empresa contaminaba el conteo de una empresa con el rol
 *       que tiene en otra.
 *
 * [OBS-CLIENTE 2026-08]: "usuarios" del dashboard = empleados
 * (User::ORG_EMPLOYEE_ROLES = admin/client/aprobador) POR ROL EN CADA
 * EMPRESA, nunca cuentas de aplicación (root, global; admin_tenant,
 * administrador de empresa). Cubre ambas ramas: tenant concreta (vía
 * Tenant::employeesQuery()) y global (vía User::whereHas('tenantRoles', ...)).
 */
class ReportsUserStatsTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $reportsService;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->reportsService = app(ReportsService::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B', 'status' => 'active']);
    }

    public function test_user_stats_counts_inactive_users(): void
    {
        User::factory()->count(2)
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'inactive']);

        User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'terminated']);

        $stats = $this->reportsService->getUserStats($this->tenantA->id);

        $this->assertEquals(4, $stats['total']);
        $this->assertEquals(2, $stats['active']);
        // Con el bug viejo esto daba 0: el where 'inactive' se acumulaba sobre
        // el mismo builder que ya tenía where('status', 'active').
        $this->assertEquals(2, $stats['inactive']);
    }

    public function test_user_stats_by_role_is_tenant_scoped(): void
    {
        // Un usuario con roles DISTINTOS (de empleado) en cada empresa: la
        // fuente de verdad por empresa es user_tenant_roles, no la unión
        // global user_roles.
        User::factory()
            ->withTenantRole($this->tenantA, 'admin', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $statsA = $this->reportsService->getUserStats($this->tenantA->id);
        $statsB = $this->reportsService->getUserStats($this->tenantB->id);

        $this->assertEquals(1, $statsA['by_role']['admin'] ?? 0);
        $this->assertArrayNotHasKey('client', $statsA['by_role']);

        $this->assertEquals(1, $statsB['by_role']['client'] ?? 0);
        $this->assertArrayNotHasKey('admin', $statsB['by_role']);
    }

    public function test_tenant_branch_excludes_admin_tenant_from_totals_and_by_role(): void
    {
        // admin_tenant es una cuenta de aplicación, no un empleado: no debe
        // sumar en total/active/inactive ni aparecer en by_role.
        User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);

        User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $stats = $this->reportsService->getUserStats($this->tenantA->id);

        $this->assertEquals(1, $stats['total'], 'admin_tenant no debe contarse como empleado');
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(0, $stats['inactive']);
        $this->assertEquals(1, $stats['by_role']['client'] ?? 0);
        $this->assertArrayNotHasKey('admin_tenant', $stats['by_role']);
    }

    public function test_tenant_branch_counts_dual_role_user_once_in_total(): void
    {
        // Un usuario con roles admin+client en la MISMA empresa cuenta una
        // sola vez en el total (distinct('users.id') de employeesQuery()),
        // pero aparece bajo AMBOS roles en by_role.
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin', true)
            ->create(['status' => 'active']);
        \App\Models\UserTenantRole::create([
            'user_id' => $user->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => \App\Models\Role::where('name', 'client')->first()->id,
        ]);

        $stats = $this->reportsService->getUserStats($this->tenantA->id);

        $this->assertEquals(1, $stats['total']);
        $this->assertEquals(1, $stats['by_role']['admin'] ?? 0);
        $this->assertEquals(1, $stats['by_role']['client'] ?? 0);
    }

    public function test_global_branch_counts_multi_tenant_user_once(): void
    {
        // Un 'client' en 2 empresas cuenta 1 sola vez en el total global.
        User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $stats = $this->reportsService->getUserStats();

        $this->assertEquals(1, $stats['total']);
        $this->assertEquals(1, $stats['active']);
        $this->assertEquals(1, $stats['by_role']['client'] ?? 0);
    }

    public function test_global_branch_excludes_root_and_admin_tenant(): void
    {
        User::factory()->root()->create(['status' => 'active']);
        User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);
        User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $stats = $this->reportsService->getUserStats();

        $this->assertEquals(1, $stats['total'], 'Solo el client cuenta como empleado; root y admin_tenant quedan fuera');
        $this->assertEquals(1, $stats['by_role']['client'] ?? 0);
        $this->assertArrayNotHasKey('root', $stats['by_role']);
        $this->assertArrayNotHasKey('admin_tenant', $stats['by_role']);
    }
}
