<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for Issue 2: Admin user should only see users from their assigned tenant.
 * Verifies that the TenantFilter middleware and TenantFilterScope correctly restrict
 * non-root users to their own tenants.
 */
class TenantFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $root;
    private User $adminTenantA;
    private User $adminTenantB;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'IPDA PERU', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Otra Empresa', 'status' => 'active']);

        // Root user - should see ALL tenants
        $this->root = User::factory()->root()->create(['status' => 'active']);

        $adminRoleId = Role::where('name', 'admin')->first()->id;

        // Admin assigned ONLY to tenant A
        $this->adminTenantA = User::factory()->admin()->create(['status' => 'active']);
        $this->adminTenantA->tenants()->attach($this->tenantA->id, ['is_primary' => true]);
        // Los permisos operativos se resuelven por empresa (user_tenant_roles):
        // sin esta fila, UserController::index() (que ahora autoriza
        // 'users.view_list' contra la empresa activa) denegaría al admin
        // aunque tenga el rol global de respaldo.
        UserTenantRole::create([
            'user_id' => $this->adminTenantA->id,
            'tenant_id' => $this->tenantA->id,
            'role_id' => $adminRoleId,
        ]);

        // Admin assigned ONLY to tenant B
        $this->adminTenantB = User::factory()->admin()->create(['status' => 'active']);
        $this->adminTenantB->tenants()->attach($this->tenantB->id, ['is_primary' => true]);
        UserTenantRole::create([
            'user_id' => $this->adminTenantB->id,
            'tenant_id' => $this->tenantB->id,
            'role_id' => $adminRoleId,
        ]);

        // Create users in each tenant
        $this->createUsersInTenant($this->tenantA, 3);
        $this->createUsersInTenant($this->tenantB, 2);
    }

    private function createUsersInTenant(Tenant $tenant, int $count): void
    {
        User::factory()->count($count)->client()->create(['status' => 'active'])->each(function ($user) use ($tenant) {
            $user->tenants()->attach($tenant->id, ['is_primary' => true]);
        });
    }

    // ==========================================
    // Issue 2: Admin should only see their tenant
    // ==========================================

    public function test_admin_only_sees_users_from_own_tenant(): void
    {
        $response = $this->actingAs($this->adminTenantA)
            ->getJson('/api/users');

        $response->assertStatus(200);

        $userData = $response->json('data');
        $userTenantIds = collect($userData)->pluck('tenants')->flatten(1)->pluck('id')->unique();

        // Admin of tenant A should NOT see users from tenant B
        $this->assertNotContains($this->tenantB->id, $userTenantIds->toArray());
    }

    public function test_admin_with_scope_all_header_still_restricted_to_own_tenant(): void
    {
        // Issue 2: Even with X-Tenant-Scope: all, admin should be restricted
        $response = $this->actingAs($this->adminTenantA)
            ->withHeaders(['X-Tenant-Scope' => 'all'])
            ->getJson('/api/users');

        $response->assertStatus(200);

        $userData = $response->json('data');

        // Should still only see users from their own tenant(s)
        foreach ($userData as $user) {
            if (!empty($user['tenants'])) {
                $tenantIds = collect($user['tenants'])->pluck('id')->toArray();
                // At least one tenant should be the admin's tenant
                $this->assertTrue(
                    in_array($this->tenantA->id, $tenantIds),
                    "Admin saw user from tenant they don't belong to"
                );
            }
        }
    }

    public function test_root_user_can_see_all_tenants(): void
    {
        $response = $this->actingAs($this->root)
            ->getJson('/api/users');

        $response->assertStatus(200);

        $userData = $response->json('data');
        // Root should see users from both tenants
        $this->assertGreaterThanOrEqual(5, count($userData)); // At least 3 + 2 tenant users
    }

    public function test_root_with_scope_all_sees_everything(): void
    {
        $response = $this->actingAs($this->root)
            ->withHeaders(['X-Tenant-Scope' => 'all'])
            ->getJson('/api/users');

        $response->assertStatus(200);

        $userData = $response->json('data');
        $this->assertGreaterThanOrEqual(5, count($userData));
    }

    public function test_admin_with_explicit_tenant_ids_validated(): void
    {
        // Admin sends their own tenant ID - should work
        $response = $this->actingAs($this->adminTenantA)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantA->id])
            ->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_other_tenant_via_header(): void
    {
        // Admin tries to access tenant B's data by sending tenant B's ID
        $response = $this->actingAs($this->adminTenantA)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantB->id])
            ->getJson('/api/users');

        // Should be rejected (403) because admin doesn't belong to tenant B
        $response->assertStatus(403);
    }

    public function test_admin_cannot_spoof_multiple_tenants(): void
    {
        // Admin tries to send both tenants - should only get access to their own
        $response = $this->actingAs($this->adminTenantA)
            ->withHeaders(['X-Tenant-Ids' => $this->tenantA->id . ',' . $this->tenantB->id])
            ->getJson('/api/users');

        $response->assertStatus(200);

        // The middleware should have filtered out tenant B
        $userData = $response->json('data');
        foreach ($userData as $user) {
            if (!empty($user['tenants'])) {
                $tenantIds = collect($user['tenants'])->pluck('id')->toArray();
                $this->assertNotContains(
                    $this->tenantB->id,
                    $tenantIds,
                    "Admin saw user from unauthorized tenant after spoofing"
                );
            }
        }
    }
}
