<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El filtro de empresa (header X-Tenant-Ids) se guarda en el navegador y
 * sobrevive a la sesión. Cuando la anterior murió sin logout, el usuario que
 * entraba después heredaba empresas ajenas y el middleware TenantFilter le
 * respondía 403 en TODAS las peticiones, incluido el cambio de contraseña
 * obligatorio: quedaba encerrado sin forma de entrar.
 *
 * Las rutas de cuenta propia quedan exentas del filtro; en las rutas de datos
 * el 403 se mantiene, que ahí sí es una comprobación de acceso legítima.
 */
class TenantFilterAccountRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Usuario activo de una empresa, más una empresa ajena a la que no pertenece.
     *
     * @return array{0: User, 1: Tenant} [usuario, empresa ajena]
     */
    private function userWithForeignTenant(array $attributes = []): array
    {
        $ownTenant = Tenant::factory()->create(['status' => 'active']);
        $foreignTenant = Tenant::factory()->create(['status' => 'active']);

        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'must_change_password' => true,
        ], $attributes));
        $user->tenants()->attach($ownTenant->id, ['is_primary' => true]);

        return [$user, $foreignTenant];
    }

    public function test_force_change_password_funciona_con_filtro_de_empresa_ajena(): void
    {
        [$user, $foreignTenant] = $this->userWithForeignTenant();

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $foreignTenant->id)
            ->postJson('/api/password/force-change', [
                'password' => 'NuevaClave123',
                'password_confirmation' => 'NuevaClave123',
            ]);

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_rutas_de_cuenta_no_se_bloquean_por_el_filtro(): void
    {
        [$user, $foreignTenant] = $this->userWithForeignTenant();

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $foreignTenant->id)
            ->getJson('/api/me')
            ->assertStatus(200);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $foreignTenant->id)
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_rutas_de_datos_siguen_respondiendo_403_con_empresa_ajena(): void
    {
        [$user, $foreignTenant] = $this->userWithForeignTenant(['must_change_password' => false]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $foreignTenant->id)
            ->getJson('/api/documents');

        $response->assertStatus(403)
            ->assertJsonFragment([
                'message' => 'Las empresas seleccionadas no están asociadas a tu cuenta',
            ]);
    }
}
