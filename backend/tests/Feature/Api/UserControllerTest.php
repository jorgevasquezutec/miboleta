<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $root;
    private User $admin;
    private User $client;
    private Tenant $tenant;
    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->clientRole = Role::where('name', 'client')->first();

        $this->root = User::factory()->root()->create(['status' => 'active']);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        // Los permisos operativos ahora se resuelven por empresa (user_tenant_roles),
        // no por el rol global. Sin esta asignación el admin no pasa el chequeo
        // hasRoleInTenant('admin', ...) de StoreUserRequest.
        \App\Models\UserTenantRole::create([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);

        $this->client = User::factory()->client()->create(['status' => 'active']);
        $this->client->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        \App\Models\UserTenantRole::create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $this->clientRole->id,
        ]);
    }

    public function test_root_can_list_all_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->root)->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'status'],
                ],
                'meta',
            ]);
    }

    public function test_admin_can_list_tenant_users(): void
    {
        User::factory()->count(3)->create()->each(function ($user) {
            $user->tenants()->attach($this->tenant->id);
        });

        $response = $this->actingAs($this->admin)->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_client_can_list_users_in_own_tenant(): void
    {
        // Client can see users but only from their own tenant
        $response = $this->actingAs($this->client)->getJson('/api/users');

        // API allows access - returns filtered results
        $response->assertStatus(200);
    }

    public function test_root_can_view_any_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->root)->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $user->id]);
    }

    public function test_admin_can_view_tenant_user(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/users/{$this->client->id}");

        $response->assertStatus(200);
    }

    public function test_admin_cannot_view_user_from_different_tenant(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    public function test_root_can_create_user(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nuevo']);

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
    }

    public function test_admin_cannot_create_user_in_tenant(): void
    {
        // Matriz de Accesos: crear usuarios es de root ('users.create_any_role')
        // y admin_tenant ('users.create_limited_role'). 'admin' no aparece, así
        // que ya no puede — antes este test afirmaba lo contrario.
        $response = $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_admin_tenant_can_create_user_in_tenant(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_create_user_requires_email(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_requires_unique_email(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'email' => $this->client->email,
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_root_can_update_user(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id);

        $response = $this->actingAs($this->root)->putJson("/api/users/{$user->id}", [
            'name' => 'Actualizado',
            'phone' => '999888777',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Actualizado']);
    }

    public function test_admin_cannot_update_tenant_user(): void
    {
        // Matriz de Accesos: 'users.update' = root, admin_tenant. 'admin' no
        // aparece — antes este test afirmaba lo contrario.
        $response = $this->actingAs($this->admin)->putJson("/api/users/{$this->client->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['id' => $this->client->id, 'name' => 'Actualizado']);
    }

    public function test_admin_tenant_can_update_tenant_user(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/users/{$this->client->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200);
    }

    public function test_root_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->root)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        // User model uses SoftDeletes
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_tenant_user(): void
    {
        // Item 28: solo root puede eliminar usuarios (ability 'users.delete' de
        // la Matriz de Accesos). Admin y admin_tenant ya no pueden, aunque
        // compartan tenant con el usuario objetivo.
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id);

        $response = $this->actingAs($this->admin)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_root_cannot_delete_himself(): void
    {
        // La ability 'users.delete' permite a root, pero nadie puede eliminarse
        // a sí mismo: esa regla es del par (actor, objetivo) y vive en
        // UserController::destroy(), no en la matriz.
        $response = $this->actingAs($this->root)->deleteJson("/api/users/{$this->root->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $this->root->id]);
    }

    public function test_filter_users_by_search(): void
    {
        User::factory()->create([
            'name' => 'Juan',
            'email' => 'juan@test.com',
        ]);

        $response = $this->actingAs($this->root)->getJson('/api/users?search=juan');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    public function test_filter_users_by_status(): void
    {
        User::factory()->inactive()->create();

        $response = $this->actingAs($this->root)->getJson('/api/users?status=inactive');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }
}
