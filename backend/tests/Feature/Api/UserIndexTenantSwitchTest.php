<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para el fix de UserController::index: antes ignoraba el switcher
 * (X-Tenant-Ids) y devolvía usuarios de TODAS las empresas del admin, sin
 * importar cuál tuviera activa. Ahora la precedencia es:
 *   ?tenant_id (override explícito) > X-Tenant-Ids del header (default) >
 *   techo de "sus empresas" (no-root) / sin techo (root).
 */
class UserIndexTenantSwitchTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Tenant $tenantC;

    private User $ana; // admin_tenant en A (primaria) y admin en B
    private User $soloA;
    private User $soloB;
    private User $soloC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B', 'status' => 'active']);
        $this->tenantC = Tenant::factory()->create(['name' => 'Empresa C', 'status' => 'active']);

        $this->ana = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin')
            ->create(['status' => 'active']);

        $this->soloA = User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $this->soloB = User::factory()
            ->withTenantRole($this->tenantB, 'client', true)
            ->create(['status' => 'active']);

        $this->soloC = User::factory()
            ->withTenantRole($this->tenantC, 'client', true)
            ->create(['status' => 'active']);
    }

    private function idsFromResponse($response): array
    {
        return collect($response->json('data'))->pluck('id')->toArray();
    }

    public function test_users_index_defaults_to_active_tenant_from_header(): void
    {
        // Empresa activa = A: ve a soloA, no a soloB.
        $response = $this->actingAs($this->ana)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->getJson('/api/users');

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloA->id, $ids);
        $this->assertNotContains($this->soloB->id, $ids);

        // Empresa activa = B: ve a soloB, no a soloA.
        $response = $this->actingAs($this->ana)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->getJson('/api/users');

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloB->id, $ids);
        $this->assertNotContains($this->soloA->id, $ids);
    }

    public function test_users_index_without_header_falls_back_to_all_user_tenants(): void
    {
        // Retrocompat con clientes antiguos que no mandan el header: sin
        // filtro estrecho, queda el techo de "todas sus empresas".
        $response = $this->actingAs($this->ana)->getJson('/api/users');

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloA->id, $ids);
        $this->assertContains($this->soloB->id, $ids);
    }

    public function test_users_index_tenant_id_param_overrides_header(): void
    {
        // SupervisorSelector consulta ?tenant_id por CADA empresa del
        // formulario, incluidas las que no son la activa: el param debe
        // ganarle al header, no intersecarse con él.
        $response = $this->actingAs($this->ana)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->getJson('/api/users?tenant_id=' . $this->tenantA->id);

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloA->id, $ids);
        $this->assertNotContains($this->soloB->id, $ids);
    }

    public function test_users_index_tenant_id_param_cannot_reach_foreign_tenant(): void
    {
        // Ana no pertenece a C: el techo de "sus empresas" sigue aplicando
        // aunque mande ?tenant_id=C explícitamente.
        $response = $this->actingAs($this->ana)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->getJson('/api/users?tenant_id=' . $this->tenantC->id);

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertNotContains($this->soloC->id, $ids);
    }

    public function test_root_users_index_respects_switcher_header(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        // Root con el header activo en A: ve a soloA, no a soloB.
        $response = $this->actingAs($root)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->getJson('/api/users');

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloA->id, $ids);
        $this->assertNotContains($this->soloB->id, $ids);

        // Root sin header: sin techo, ve a todos.
        // withHeader() deja el header seteado en $this->defaultHeaders para
        // las siguientes requests del mismo test; hay que quitarlo a mano
        // para simular de verdad "sin header".
        $response = $this->withoutHeader('X-Tenant-Ids')
            ->actingAs($root)
            ->getJson('/api/users');

        $response->assertStatus(200);
        $ids = $this->idsFromResponse($response);

        $this->assertContains($this->soloA->id, $ids);
        $this->assertContains($this->soloB->id, $ids);
        $this->assertContains($this->soloC->id, $ids);
    }
}
