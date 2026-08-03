<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-08]: GET /api/user-batches/config alimenta el <select>
 * de rol por empresa del modal de Carga Masiva de Usuarios (available_roles).
 * root debe ver 'admin_tenant' en la lista (puede asignarlo por esta vía);
 * admin_tenant/admin no, porque BulkUserUploadService::allowedOrgRolesFor()
 * sigue negándoselo a cualquier actor que no sea root.
 */
class UserBatchConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_config_includes_admin_tenant_for_root(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        $response = $this->actingAs($root)->getJson('/api/user-batches/config');

        $response->assertStatus(200);

        $roleNames = collect($response->json('available_roles'))->pluck('name');
        $this->assertContains('admin_tenant', $roleNames);
    }

    public function test_config_excludes_admin_tenant_for_admin_tenant_actor(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $adminTenant = User::factory()
            ->admin()
            ->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $tenant->id])
            ->getJson('/api/user-batches/config');

        $response->assertStatus(200);

        $roleNames = collect($response->json('available_roles'))->pluck('name');
        $this->assertNotContains('admin_tenant', $roleNames);
        // El resto de roles operativos sigue disponible.
        $this->assertContains('client', $roleNames);
        $this->assertContains('aprobador', $roleNames);
    }
}
