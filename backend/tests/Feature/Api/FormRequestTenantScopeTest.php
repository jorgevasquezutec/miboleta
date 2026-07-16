<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REGRESIÓN: los authorize() de los FormRequests resolvían el rol con
 * getCurrentRole() (respaldo global = UNIÓN de los roles del usuario en todas
 * sus empresas). Un usuario admin en la empresa A pasaba el authorize() aunque
 * estuviera operando sobre la empresa B, donde solo es client.
 *
 * Ahora el rol se resuelve en la empresa ACTIVA de la petición
 * (header X-Tenant-Ids -> TenantFilter -> ActiveTenantResolver).
 */
class FormRequestTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $multiTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['status' => 'active']);

        // admin_tenant en A (puede crear usuarios según la matriz), solo client
        // en B. ->admin() reproduce el respaldo global que en producción
        // mantiene UserService::syncGlobalRoleFallback con la unión de roles;
        // sin él el test no probaría nada.
        $this->multiTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);
    }

    /**
     * StoreUserRequest::authorize() exige 'users.create_any_role' o
     * 'users.create_limited_role'. Operando sobre la empresa B (donde es
     * client) debe recibir 403.
     */
    public function test_store_user_request_denies_when_active_tenant_role_is_not_allowed(): void
    {
        $response = $this->actingAs($this->multiTenant)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->postJson('/api/users', [
                'name' => 'Nuevo',
                'last_name' => 'Empleado',
                'email' => 'nuevo@example.com',
                'tenant_id' => $this->tenantB->id,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Contraparte: en la empresa A sí es admin_tenant, así que authorize() pasa
     * y la petición avanza hasta la validación (422 por payload incompleto), no
     * 403. Esto evita que el test de arriba pase por un deny-all accidental.
     */
    public function test_store_user_request_allows_when_active_tenant_role_is_allowed(): void
    {
        $response = $this->actingAs($this->multiTenant)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->postJson('/api/users', [
                // Payload deliberadamente incompleto: lo que importa es que NO
                // sea 403 (authorize pasó), sino 422 (validación).
            ]);

        $response->assertStatus(422);
    }
}
