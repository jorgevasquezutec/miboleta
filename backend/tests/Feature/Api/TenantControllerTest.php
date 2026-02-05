<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
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

        $this->client = User::factory()->client()->create(['status' => 'active']);
        $this->client->tenants()->attach($this->tenant->id, ['is_primary' => true]);
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

    public function test_admin_can_view_own_tenant(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/tenants/{$this->tenant->id}");

        $response->assertStatus(200);
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

    public function test_unauthenticated_cannot_access_tenants(): void
    {
        $response = $this->getJson('/api/tenants');

        $response->assertStatus(401);
    }
}
