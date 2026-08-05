<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Document;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_has_correct_default_status(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertEquals('active', $tenant->status);
    }

    public function test_tenant_can_be_inactive(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->assertEquals('inactive', $tenant->status);
    }

    public function test_tenant_can_have_users(): void
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $tenant->users()->attach($user->id);
        }

        $this->assertCount(3, $tenant->users);
    }

    public function test_tenant_can_have_documents(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Document::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(5, $tenant->documents);
    }

    public function test_tenant_has_ruc(): void
    {
        $tenant = Tenant::factory()->create([
            'ruc' => '12345678901',
        ]);

        $this->assertEquals('12345678901', $tenant->ruc);
    }

    /**
     * Fuente única de los contadores de empleados (RP1-C), compartida por
     * TenantService::transformTenantForList y TenantResource (ver
     * TenantControllerTest::test_employee_counts_match_between_list_and_resource_views
     * para la prueba de que ambas vías coinciden).
     *
     * [Área 1] El conteo ahora es por ROL DE EMPLEADO (User::
     * ORG_EMPLOYEE_ROLES) en user_tenant_roles, no solo membresía en
     * user_tenants: se usa withTenantRole() para que cada usuario quede
     * contado.
     */
    public function test_employee_counts_formula(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 3]);
        User::factory()->count(5)->withTenantRole($tenant, 'client')->create();

        $counts = $tenant->employeeCounts();

        $this->assertSame(5, $counts['current_employee_count']);
        $this->assertSame(3, $counts['initial_employee_count']);
        // "La carga inicial es la primera, luego de eso ya todo es nuevo":
        // subsequent = current - initial = 5 - 3 = 2.
        $this->assertSame(2, $counts['subsequent_employee_count']);
    }

    /**
     * subsequent_employee_count nunca debe ser negativo (p.ej. si se dieron
     * de baja usuarios después de la carga inicial y current < initial).
     */
    public function test_employee_counts_subsequent_never_negative(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 10]);
        User::factory()->withTenantRole($tenant, 'client')->create();

        $counts = $tenant->employeeCounts();

        $this->assertSame(1, $counts['current_employee_count']);
        $this->assertSame(10, $counts['initial_employee_count']);
        $this->assertSame(0, $counts['subsequent_employee_count']);
    }

    /**
     * [Área 1] admin_tenant es una cuenta de aplicación (administrador de la
     * empresa), no un empleado: un usuario con ÚNICAMENTE ese rol en el
     * tenant no debe contarse.
     */
    public function test_employee_counts_excludes_pure_admin_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->withTenantRole($tenant, 'admin_tenant')->create();

        $this->assertSame(0, $tenant->employeeCounts()['current_employee_count']);
    }

    /**
     * Regla "admin_tenant domina": quien es admin_tenant de una empresa es
     * cuenta de aplicación ahí, aunque también tenga un rol de empleado en la
     * misma empresa — NO cuenta como empleado. Sí aparece en
     * app_accounts_count (columna "Cuentas de Aplicación", solo root).
     */
    public function test_employee_counts_excludes_dual_admin_tenant_even_with_employee_role(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()
            ->withTenantRole($tenant, 'admin_tenant')
            ->withTenantRole($tenant, 'client')
            ->create();

        $counts = $tenant->employeeCounts();
        $this->assertSame(0, $counts['current_employee_count']);
        $this->assertSame(1, $counts['app_accounts_count']);
    }

    /**
     * app_accounts_count cuenta los admin_tenant de la empresa (una vez cada
     * uno) y no se solapa con current_employee_count.
     */
    public function test_app_accounts_count_counts_admin_tenants_of_the_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()->withTenantRole($tenant, 'admin_tenant')->create();
        User::factory()->withTenantRole($tenant, 'client')->create();

        $counts = $tenant->employeeCounts();
        $this->assertSame(1, $counts['app_accounts_count']);
        $this->assertSame(1, $counts['current_employee_count']);
    }

    /**
     * [Área 1] Un usuario con dos roles de empleado (admin + client) en la
     * MISMA empresa cuenta una sola vez (->distinct('users.id')).
     */
    public function test_employee_counts_counts_multi_role_employee_once(): void
    {
        $tenant = Tenant::factory()->create();
        User::factory()
            ->withTenantRole($tenant, 'admin')
            ->withTenantRole($tenant, 'client')
            ->create();

        $this->assertSame(1, $tenant->employeeCounts()['current_employee_count']);
    }

    /**
     * [Área 1] Ser miembro de la empresa (user_tenants) no basta: sin una
     * fila en user_tenant_roles con un rol de empleado, no cuenta.
     */
    public function test_employee_counts_excludes_member_without_tenant_role(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id);

        $this->assertSame(0, $tenant->employeeCounts()['current_employee_count']);
    }

    /**
     * [Área 1] El rol de empleado se evalúa POR EMPRESA: un 'client' en OTRO
     * tenant no debe afectar el conteo de este tenant.
     */
    public function test_employee_counts_ignores_employee_role_in_other_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        User::factory()->withTenantRole($otherTenant, 'client')->create();

        $this->assertSame(0, $tenant->employeeCounts()['current_employee_count']);
    }
}
