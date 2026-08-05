<?php

namespace Tests\Feature\Migrations;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * [Área 2] Prueba la migración de datos que corrige tenants.
 * initial_employee_count restando los admin_tenant puros que el snapshot
 * viejo (fórmula pre-[Área 1]) contaba de más. Se ejecuta la migración
 * directamente vía `(require ...)->up()` -no hay comando artisan disponible
 * para "correr una sola migración de datos" fuera de `migrate`, y este
 * patrón evita depender del ledger de migraciones para el test-.
 */
class RecalculateInitialEmployeeCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function runMigration(): void
    {
        (require base_path('database/migrations/2026_08_04_000001_recalculate_initial_employee_count_without_app_accounts.php'))->up();
    }

    /**
     * Caso 1: initial=5, con 2 admin_tenant puros y 1 dual
     * admin_tenant+client. Regla "admin_tenant domina": el dual TAMBIÉN se
     * resta (la fórmula nueva no lo cuenta como empleado de esta empresa)
     * -> 5 - 3 = 2.
     */
    public function test_subtracts_all_admin_tenant_members_from_snapshot(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 5]);

        User::factory()->count(2)->withTenantRole($tenant, 'admin_tenant')->create();

        User::factory()
            ->withTenantRole($tenant, 'admin_tenant')
            ->withTenantRole($tenant, 'client')
            ->create();

        $this->runMigration();

        $this->assertSame(2, $tenant->fresh()->initial_employee_count);
    }

    /**
     * Caso 2: tenant con snapshot sin fijar (initial_employee_count = 0) se
     * salta -no hay nada que corregir; su primer batch lo fijará con la
     * fórmula nueva-.
     */
    public function test_skips_tenants_with_unset_snapshot(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 0]);

        User::factory()->withTenantRole($tenant, 'admin_tenant')->create();

        $this->runMigration();

        $this->assertSame(0, $tenant->fresh()->initial_employee_count);
    }

    /**
     * Caso 3: el recálculo puede dejar el snapshot en 0 (más admin_tenant
     * puros que initial_employee_count) sin fallar -"floor" en 0, nunca
     * negativo-. Ese 0 reabre el snapshot a propósito (ver guard `!empty`
     * en UserBatch::syncInitialEmployeeCounts).
     */
    public function test_floors_at_zero_without_failing(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 1]);

        User::factory()->count(3)->withTenantRole($tenant, 'admin_tenant')->create();

        $this->runMigration();

        $this->assertSame(0, $tenant->fresh()->initial_employee_count);
    }

    /**
     * Caso 4: tenant sin ningún admin_tenant queda intacto (nada que
     * restar).
     */
    public function test_leaves_tenant_without_admin_tenant_untouched(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 4]);

        User::factory()->count(4)->withTenantRole($tenant, 'client')->create();

        $this->runMigration();

        $this->assertSame(4, $tenant->fresh()->initial_employee_count);
    }

    /**
     * Diagnóstico: un miembro (user_tenants) sin ninguna fila en
     * user_tenant_roles para esa empresa se loguea como warning (no
     * bloquea la migración ni cambia el snapshot).
     */
    public function test_logs_warning_for_members_without_tenant_role(): void
    {
        Log::spy();

        $tenant = Tenant::factory()->create(['initial_employee_count' => 0]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id);

        $this->runMigration();

        Log::shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context) use ($tenant, $user) {
                return str_contains($message, 'sin rol en user_tenant_roles')
                    && $context['count'] === 1
                    && $context['pairs'][0]['tenant_id'] === $tenant->id
                    && $context['pairs'][0]['user_id'] === $user->id;
            })
            ->once();
    }
}
