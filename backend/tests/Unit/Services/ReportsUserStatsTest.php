<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para los 2 bugs de ReportsService::getUserStats():
 *   (a) reutilizaba un builder entre métricas: los where se acumulaban sobre
 *       la misma instancia y 'inactive' quedaba insatisfacible (siempre 0).
 *   (b) by_role unía contra la tabla global user_roles (la UNIÓN de roles del
 *       usuario en TODAS sus empresas) en vez de user_tenant_roles, así que un
 *       usuario multi-empresa contaminaba el conteo de una empresa con el rol
 *       que tiene en otra.
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
        // Un usuario con roles DISTINTOS en cada empresa: la fuente de verdad
        // por empresa es user_tenant_roles, no la unión global user_roles.
        User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin')
            ->create(['status' => 'active']);

        $statsA = $this->reportsService->getUserStats($this->tenantA->id);
        $statsB = $this->reportsService->getUserStats($this->tenantB->id);

        $this->assertEquals(1, $statsA['by_role']['admin_tenant'] ?? 0);
        $this->assertArrayNotHasKey('admin', $statsA['by_role']);

        $this->assertEquals(1, $statsB['by_role']['admin'] ?? 0);
        $this->assertArrayNotHasKey('admin_tenant', $statsB['by_role']);
    }
}
