<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\TenantMailerService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantServiceTest extends TestCase
{
    use RefreshDatabase;

    private TenantService $tenantService;
    private User $root;
    private User $admin;
    private User $client;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantService = new TenantService(new TenantMailerService(), new AuditService());

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->root = User::factory()->root()->create(['status' => 'active']);

        // El rol operativo se asigna POR EMPRESA (user_tenant_roles): es lo que
        // se autoriza. Los states ->admin()/->client() solo escriben el rol
        // global de respaldo (display), que ya no autoriza nada.
        $this->admin = User::factory()->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);

        $this->client = User::factory()->client()
            ->withTenantRole($this->tenant, 'client', true)
            ->create(['status' => 'active']);
    }

    public function test_root_can_see_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $tenants = $this->tenantService->getTenants($this->root);

        $this->assertEquals(4, $tenants->total()); // 3 + 1 from setUp
    }

    public function test_admin_sees_only_own_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $tenants = $this->tenantService->getTenants($this->admin);

        $this->assertEquals(1, $tenants->total());
    }

    public function test_get_tenant_returns_tenant_for_root(): void
    {
        $tenant = $this->tenantService->getTenant($this->tenant->id, $this->root);

        $this->assertEquals($this->tenant->id, $tenant->id);
    }

    public function test_get_tenant_returns_tenant_for_member(): void
    {
        $tenant = $this->tenantService->getTenant($this->tenant->id, $this->admin);

        $this->assertEquals($this->tenant->id, $tenant->id);
    }

    public function test_get_tenant_throws_for_non_member(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->expectException(UnauthorizedAccessException::class);

        $this->tenantService->getTenant($otherTenant->id, $this->admin);
    }

    public function test_create_tenant_with_valid_data(): void
    {
        $data = [
            'name' => 'Nueva Empresa',
            'ruc' => '12345678901',
            'business_name' => 'Nueva Empresa S.A.C.',
            'address' => 'Av. Principal 123',
            'phone' => '999888777',
            'status' => 'active',
        ];

        $tenant = $this->tenantService->createTenant($data);

        $this->assertEquals('Nueva Empresa', $tenant->name);
        $this->assertEquals('12345678901', $tenant->ruc);
        $this->assertEquals('active', $tenant->status);
    }

    public function test_update_tenant(): void
    {
        $updatedTenant = $this->tenantService->updateTenant($this->tenant, [
            'name' => 'Empresa Actualizada',
            'phone' => '111222333',
        ]);

        $this->assertEquals('Empresa Actualizada', $updatedTenant->name);
        $this->assertEquals('111222333', $updatedTenant->phone);
    }

    public function test_delete_tenant_by_root(): void
    {
        $tenantToDelete = Tenant::factory()->create();

        $result = $this->tenantService->deleteTenant($tenantToDelete->id, $this->root);

        $this->assertTrue($result);
        // Tenant model uses SoftDeletes
        $this->assertSoftDeleted('tenants', ['id' => $tenantToDelete->id]);
    }

    public function test_delete_tenant_denied_for_non_root(): void
    {
        $this->expectException(UnauthorizedAccessException::class);

        $this->tenantService->deleteTenant($this->tenant->id, $this->admin);
    }

    public function test_get_tenant_users(): void
    {
        // Decisión C1: para no-root, getTenantUsers() excluye la fila propia
        // del solicitante (además de cualquier admin_tenant). $this->admin
        // consultando SU PROPIO tenant ya no se ve a sí mismo en el
        // resultado; solo queda $this->client.
        $users = $this->tenantService->getTenantUsers($this->tenant->id, $this->admin);

        $this->assertEquals(1, $users->count());
        $this->assertTrue($users->contains('id', $this->client->id));
        $this->assertFalse($users->contains('id', $this->admin->id));
    }

    public function test_get_tenant_users_includes_requester_for_root(): void
    {
        // Root no tiene la exclusión C1: ve el catálogo completo del tenant.
        $users = $this->tenantService->getTenantUsers($this->tenant->id, $this->root);

        $this->assertGreaterThanOrEqual(2, $users->count()); // admin + client
    }

    public function test_get_tenant_users_denied_for_non_member(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->expectException(UnauthorizedAccessException::class);

        $this->tenantService->getTenantUsers($otherTenant->id, $this->admin);
    }

    public function test_add_user_to_tenant(): void
    {
        $newUser = User::factory()->client()->create();

        $result = $this->tenantService->addUserToTenant($this->tenant->id, $newUser->id);

        $this->assertTrue($result['success']);
        $this->assertTrue($this->tenant->hasUser($newUser));
    }

    public function test_add_user_already_in_tenant_fails(): void
    {
        $result = $this->tenantService->addUserToTenant($this->tenant->id, $this->client->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ya está asignado', $result['message']);
    }

    public function test_remove_user_from_tenant(): void
    {
        $userToRemove = User::factory()->client()->create();
        $this->tenant->users()->attach($userToRemove->id, ['is_primary' => false]);

        $result = $this->tenantService->removeUserFromTenant(
            $this->tenant->id,
            $userToRemove->id,
            $this->admin
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($this->tenant->hasUser($userToRemove->fresh()));
    }

    public function test_cannot_remove_user_with_primary_tenant(): void
    {
        $result = $this->tenantService->removeUserFromTenant(
            $this->tenant->id,
            $this->client->id,
            $this->admin
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('primario', $result['message']);
    }

    public function test_client_cannot_remove_users_from_his_own_tenant(): void
    {
        // REGRESIÓN: canManageTenantUsers() era `root || $tenant->hasUser($user)`,
        // sin mirar el rol, así que un client podía sacar a cualquiera de su
        // propia empresa. Ahora exige 'tenants.assign_users' (root, admin).
        $this->assertFalse($this->tenantService->canManageTenantUsers($this->client, $this->tenant));

        $this->expectException(UnauthorizedAccessException::class);

        $this->tenantService->removeUserFromTenant(
            (string) $this->tenant->id,
            (string) $this->admin->id,
            $this->client
        );
    }

    public function test_admin_of_another_tenant_cannot_remove_users(): void
    {
        // El rol se resuelve en la empresa DEL RECURSO: ser admin en la empresa
        // A no da permisos sobre la B.
        $otherTenant = Tenant::factory()->create(['status' => 'active']);

        $this->assertFalse($this->tenantService->canManageTenantUsers($this->admin, $otherTenant));
    }

    public function test_can_access_tenant_root(): void
    {
        $otherTenant = Tenant::factory()->create();

        $canAccess = $this->tenantService->canAccessTenant($this->root, $otherTenant);

        $this->assertTrue($canAccess);
    }

    public function test_can_access_tenant_member(): void
    {
        $canAccess = $this->tenantService->canAccessTenant($this->admin, $this->tenant);

        $this->assertTrue($canAccess);
    }

    public function test_cannot_access_tenant_non_member(): void
    {
        $otherTenant = Tenant::factory()->create();

        $canAccess = $this->tenantService->canAccessTenant($this->admin, $otherTenant);

        $this->assertFalse($canAccess);
    }

    public function test_transform_tenant_for_list(): void
    {
        $transformed = $this->tenantService->transformTenantForList($this->tenant);

        $this->assertArrayHasKey('id', $transformed);
        $this->assertArrayHasKey('name', $transformed);
        $this->assertArrayHasKey('ruc', $transformed);
        $this->assertArrayHasKey('status', $transformed);
        $this->assertArrayHasKey('users_count', $transformed);
    }

    public function test_filter_tenants_by_search(): void
    {
        Tenant::factory()->create(['name' => 'Empresa ABC']);
        Tenant::factory()->create(['name' => 'Empresa XYZ']);

        $tenants = $this->tenantService->getTenants($this->root, ['search' => 'ABC']);

        $this->assertEquals(1, $tenants->total());
        $this->assertEquals('Empresa ABC', $tenants->first()->name);
    }

    public function test_filter_tenants_by_status(): void
    {
        Tenant::factory()->create(['status' => 'inactive']);

        $activeTenants = $this->tenantService->getTenants($this->root, ['status' => 'active']);
        $inactiveTenants = $this->tenantService->getTenants($this->root, ['status' => 'inactive']);

        $this->assertEquals(1, $activeTenants->total());
        $this->assertEquals(1, $inactiveTenants->total());
    }
}
