<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Blinda la fuente única de verdad de autorización (config/access_matrix.php):
 *
 * 1. Que cada ability del config esté registrada como Gate y que el Gate
 *    responda EXACTAMENTE lo que dice el config (sin deriva).
 * 2. Que el rol se resuelva SOLO dentro de la empresa evaluada, sin caer al
 *    respaldo global `user_roles` (la fuga de privilegios entre empresas).
 */
class AccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /** Todos los roles operativos + root. */
    private const ALL_ROLES = ['root', 'admin', 'admin_tenant', 'aprobador', 'client'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_every_ability_in_config_is_registered_as_a_gate(): void
    {
        $abilities = array_keys(config('access_matrix'));

        $this->assertNotEmpty($abilities, 'La matriz de accesos no debe estar vacía.');

        foreach ($abilities as $ability) {
            $this->assertTrue(
                Gate::has($ability),
                "La ability '{$ability}' del config no está registrada como Gate (ver AppServiceProvider::registerAccessMatrixGates)."
            );
        }
    }

    /**
     * Para cada ability × cada rol: el Gate debe coincidir con el config.
     * Detecta cualquier deriva entre la matriz y lo que el sistema autoriza.
     */
    public function test_gates_match_the_config_for_every_ability_and_role(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $matrix = config('access_matrix');

        foreach (self::ALL_ROLES as $roleName) {
            $user = $roleName === 'root'
                ? User::factory()->root()->create()
                : User::factory()->withTenantRole($tenant, $roleName, true)->create();

            foreach ($matrix as $ability => $allowedRoles) {
                $expected = in_array($roleName, $allowedRoles, true);
                $actual = Gate::forUser($user)->allows($ability, $tenant->id);

                $this->assertSame(
                    $expected,
                    $actual,
                    "Deriva en '{$ability}' para el rol '{$roleName}': el config dice "
                    . ($expected ? 'PERMITE' : 'DENIEGA') . ' pero el Gate dice '
                    . ($actual ? 'PERMITE' : 'DENIEGA') . '.'
                );
            }
        }
    }

    public function test_unknown_ability_is_denied_fail_closed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->withTenantRole($tenant, 'admin', true)->create();

        $this->assertFalse($user->hasAbility('modulo.inventado', $tenant->id));
    }

    /**
     * EL TEST CLAVE: el rol se resuelve dentro de la empresa evaluada, no con la
     * unión global de roles. Sin esto, un admin en la empresa A pasaba checks
     * operando sobre la empresa B.
     */
    public function test_role_is_resolved_per_tenant_and_does_not_leak_across_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['status' => 'active']);
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        // admin en A, client en B (el escenario real: Ana Torres).
        $user = User::factory()
            ->withTenantRole($tenantA, 'admin', true)
            ->withTenantRole($tenantB, 'client')
            ->create();

        // 'audit.view' = [root, admin, admin_tenant]
        $this->assertTrue(
            Gate::forUser($user)->allows('audit.view', $tenantA->id),
            'Como admin en la empresa A debería poder ver auditoría de A.'
        );
        $this->assertFalse(
            Gate::forUser($user)->allows('audit.view', $tenantB->id),
            'FUGA: como client en la empresa B NO debe ver la auditoría de B, '
            . 'aunque sea admin en A.'
        );
    }

    public function test_role_for_tenant_has_no_global_fallback(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $user = User::factory()->withTenantRole($tenantA, 'admin', true)->create();

        $this->assertSame('admin', User::roleForTenant($user, $tenantA->id));
        // Sin rol en B => null (fail-closed), NO el rol global de A.
        $this->assertNull(User::roleForTenant($user, $tenantB->id));
        // Sin empresa => null. getCurrentRole() en cambio caería al global.
        $this->assertNull(User::roleForTenant($user, null));
    }

    public function test_root_is_global_and_not_a_wildcard(): void
    {
        $tenant = Tenant::factory()->create();
        $root = User::factory()->root()->create();

        // Root resuelve 'root' sin pertenecer a ninguna empresa.
        $this->assertSame('root', User::roleForTenant($root, $tenant->id));
        $this->assertSame('root', User::roleForTenant($root, null));

        // Pero NO es comodín: la matriz lo excluye de acciones operativas.
        $this->assertTrue(Gate::forUser($root)->allows('audit.view', $tenant->id));
        $this->assertFalse(
            Gate::forUser($root)->allows('documents.sign_2fa', $tenant->id),
            'La matriz excluye a root de firmar documentos; no debe haber bypass de root.'
        );
    }
}
