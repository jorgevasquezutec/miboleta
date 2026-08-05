<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $root;
    private User $admin;
    private User $client;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->root = User::factory()->root()->create(['status' => 'active']);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        // Los permisos operativos se resuelven por empresa (user_tenant_roles),
        // no por el rol global: sin esta fila, TenantController::users() (que
        // ahora autoriza 'users.view_list' contra ESTE tenant) denegaría a
        // $this->admin aunque sea 'admin' global.
        UserTenantRole::create([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);

        $this->client = User::factory()->client()->create(['status' => 'active']);
        $this->client->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        // [Área 1] igual que con $this->admin arriba: sin esta fila en
        // user_tenant_roles, Tenant::employeesQuery() (fuente única del
        // conteo de empleados) no contaría a $this->client como empleado de
        // este tenant, aunque sea miembro (user_tenants).
        UserTenantRole::create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'client')->first()->id,
        ]);
    }

    public function test_root_can_list_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->actingAs($this->root)->getJson('/api/tenants');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'ruc', 'status'],
                ],
                'meta',
            ]);

        $this->assertEquals(4, $response->json('meta.total'));
    }

    public function test_admin_sees_only_own_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->getJson('/api/tenants');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
    }

    public function test_root_can_view_any_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->actingAs($this->root)->getJson("/api/tenants/{$tenant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $tenant->id]);
    }

    public function test_admin_cannot_view_tenant_detail(): void
    {
        // Obs-3: show() ahora exige 'tenants.view' (root, admin_tenant), no
        // solo la membresía (canAccessTenant). 'admin' (Admin Empleados) solo
        // necesita LISTAR tenants para asignarlos a un usuario — cubierto por
        // 'tenants.assign_users' en index() — nunca vio el detalle/perfil de
        // una organización a través de la matriz; antes pasaba igual con
        // cualquier tenant del que fuera miembro, sin mirar el rol.
        $response = $this->actingAs($this->admin)->getJson("/api/tenants/{$this->tenant->id}");

        $response->assertStatus(403);
    }

    public function test_admin_cannot_view_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/tenants/{$otherTenant->id}");

        $response->assertStatus(403);
    }

    public function test_root_can_create_tenant(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/tenants', [
            'name' => 'Nueva Empresa',
            'ruc' => '12345678901',
            'business_name' => 'Nueva Empresa S.A.C.',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nueva Empresa']);

        $this->assertDatabaseHas('tenants', ['ruc' => '12345678901']);
    }

    public function test_admin_cannot_create_tenant(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/tenants', [
            'name' => 'Nueva Empresa',
            'ruc' => '12345678901',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_tenant_requires_name(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/tenants', [
            'ruc' => '12345678901',
        ]);

        $response->assertStatus(422);
        // CustomFormRequest returns {message: "error"} format
        $this->assertStringContainsString('nombre', strtolower($response->json('message')));
    }

    public function test_create_tenant_requires_valid_ruc(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/tenants', [
            'name' => 'Nueva Empresa',
            'ruc' => '123', // Invalid RUC - must be 11 characters
        ]);

        $response->assertStatus(422);
        // CustomFormRequest returns {message: "error"} format
        $this->assertStringContainsString('RUC', $response->json('message'));
    }

    public function test_root_can_update_tenant(): void
    {
        $response = $this->actingAs($this->root)->putJson("/api/tenants/{$this->tenant->id}", [
            'name' => 'Empresa Actualizada',
            'phone' => '999888777',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Empresa Actualizada']);
    }

    /**
     * Los contadores de empleados (initial/current/subsequent) se calculan
     * en dos lugares distintos: TenantService::transformTenantForList
     * (index/show) y TenantResource (store/update). Antes cada uno tenía su
     * propia copia de la fórmula; ahora ambos delegan en
     * Tenant::employeeCounts(), así que deben devolver EXACTAMENTE los
     * mismos valores para el mismo tenant.
     */
    public function test_employee_counts_match_between_list_and_resource_views(): void
    {
        $this->tenant->update(['initial_employee_count' => 1]);
        // $this->tenant ya tiene 2 usuarios adjuntos (admin, client) desde
        // el setUp: current=2, initial=1, subsequent=max(0, 2-1)=1.

        $indexResponse = $this->actingAs($this->root)->getJson('/api/tenants');
        $indexResponse->assertStatus(200);
        $fromIndex = collect($indexResponse->json('data'))
            ->firstWhere('id', $this->tenant->id);

        $updateResponse = $this->actingAs($this->root)->putJson("/api/tenants/{$this->tenant->id}", [
            'name' => $this->tenant->name,
        ]);
        $updateResponse->assertStatus(200);
        $fromResource = $updateResponse->json('data');

        $this->assertSame(2, $fromIndex['current_employee_count']);
        $this->assertSame(1, $fromIndex['initial_employee_count']);
        $this->assertSame(1, $fromIndex['subsequent_employee_count']);

        $this->assertSame($fromIndex['current_employee_count'], $fromResource['current_employee_count']);
        $this->assertSame($fromIndex['initial_employee_count'], $fromResource['initial_employee_count']);
        $this->assertSame($fromIndex['subsequent_employee_count'], $fromResource['subsequent_employee_count']);
    }

    public function test_admin_cannot_update_other_tenant(): void
    {
        // Admin can update their OWN tenant, but not OTHER tenants
        $otherTenant = Tenant::factory()->create();

        $response = $this->actingAs($this->admin)->putJson("/api/tenants/{$otherTenant->id}", [
            'name' => 'Empresa Actualizada',
        ]);

        $response->assertStatus(403);
    }

    public function test_root_can_delete_tenant(): void
    {
        $tenantToDelete = Tenant::factory()->create();

        $response = $this->actingAs($this->root)->deleteJson("/api/tenants/{$tenantToDelete->id}");

        $response->assertStatus(200);
        // Tenant model uses SoftDeletes
        $this->assertSoftDeleted('tenants', ['id' => $tenantToDelete->id]);
    }

    public function test_admin_cannot_delete_tenant(): void
    {
        $response = $this->actingAs($this->admin)->deleteJson("/api/tenants/{$this->tenant->id}");

        $response->assertStatus(403);
    }

    public function test_filter_tenants_by_search(): void
    {
        Tenant::factory()->create(['name' => 'ABC Corp']);

        $response = $this->actingAs($this->root)->getJson('/api/tenants?search=ABC');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_filter_tenants_by_status(): void
    {
        Tenant::factory()->create(['status' => 'inactive']);

        $response = $this->actingAs($this->root)->getJson('/api/tenants?status=inactive');

        $response->assertStatus(200);
    }

    public function test_get_tenant_users(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/tenants/{$this->tenant->id}/users");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email'],
                ],
            ]);
    }

    public function test_get_tenant_users_hides_requester_and_admin_tenant_rows_for_non_root(): void
    {
        // E1 + C1: TenantController::users() es la ruta paralela a
        // UserController::index() y comparte la misma exclusión.
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant')
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->getJson("/api/tenants/{$this->tenant->id}/users");

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertNotContains($this->admin->id, $ids);
        $this->assertNotContains($adminTenant->id, $ids);
        $this->assertContains($this->client->id, $ids);
    }

    public function test_get_tenant_users_denied_for_client(): void
    {
        // E1 [seguridad]: antes esta ruta solo pasaba por canAccessTenant()
        // (membresía), sin mirar la ability 'users.view_list'.
        $response = $this->actingAs($this->client)->getJson("/api/tenants/{$this->tenant->id}/users");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_tenants(): void
    {
        $response = $this->getJson('/api/tenants');

        $response->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Obs-3: acceso de SOLO LECTURA de admin_tenant al módulo Empresas
    |--------------------------------------------------------------------------
    | 'tenants.view' => ['root', 'admin_tenant']. El scoping de QUÉ empresas ve
    | (solo las que administra) ya vive en TenantService::getTenants()/
    | canAccessTenant() y no cambia aquí; lo que se prueba es la autorización.
    */

    public function test_admin_tenant_can_list_only_administered_tenants(): void
    {
        Tenant::factory()->count(2)->create();

        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->getJson('/api/tenants');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('meta.total'));
        $this->assertEquals($this->tenant->id, $response->json('data.0.id'));
    }

    public function test_client_cannot_list_tenants(): void
    {
        $response = $this->actingAs($this->client)->getJson('/api/tenants');

        $response->assertStatus(403);
    }

    public function test_aprobador_cannot_list_tenants(): void
    {
        $aprobador = User::factory()
            ->withTenantRole($this->tenant, 'aprobador', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($aprobador)->getJson('/api/tenants');

        $response->assertStatus(403);
    }

    public function test_admin_tenant_can_view_own_tenant(): void
    {
        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->getJson("/api/tenants/{$this->tenant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $this->tenant->id]);
    }

    public function test_admin_tenant_cannot_view_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->getJson("/api/tenants/{$otherTenant->id}");

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_create_tenant(): void
    {
        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson('/api/tenants', [
            'name' => 'Nueva Empresa',
            'ruc' => '12345678901',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_update_own_tenant(): void
    {
        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/tenants/{$this->tenant->id}", [
            'name' => 'Empresa Actualizada',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_delete_own_tenant(): void
    {
        $adminTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->deleteJson("/api/tenants/{$this->tenant->id}");

        $response->assertStatus(403);
    }
}
