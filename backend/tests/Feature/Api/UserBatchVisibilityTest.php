<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FASE 4 (OBS-CLIENTE 2026-07, D3/D6): UserBatch ya no aplica
 * TenantFilterScope (ver UserBatch::booted()). Antes, un batch quedaba
 * persistido contra "el primer tenant del usuario" en vez de la empresa
 * ACTIVA del switcher (bug real de UserBatchController::store/uploadData), y
 * el scope lo hacía invisible en index()/show() en cuanto la empresa activa
 * dejaba de coincidir con ese tenant_id -para root, SIEMPRE, porque su
 * tenant_id es NULL-. La visibilidad correcta la resuelven explícitamente
 * UserBatchController::index() (created_by OR tenants administrados) y
 * ::authorizeBatchOwnership() (show).
 */
class UserBatchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminTenant; // administra A y B

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['status' => 'active']);

        $this->adminTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin_tenant')
            ->create(['status' => 'active']);
    }

    private function makeBatch(?int $tenantId, int $createdBy, array $overrides = []): UserBatch
    {
        return UserBatch::create(array_merge([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $createdBy,
            'original_filename' => 'usuarios.xlsx',
            'file_size' => 100,
            'status' => 'completed',
            'total_rows' => 5,
            'created_users' => 5,
        ], $overrides));
    }

    public function test_batch_created_in_non_active_tenant_is_visible_in_index(): void
    {
        // El batch quedó guardado contra la Empresa B, pero el switcher del
        // request está en la Empresa A (exactamente el bug reportado por el
        // cliente: tenant_id activo != tenant_id del batch).
        $batch = $this->makeBatch($this->tenantB->id, $this->adminTenant->id);

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantA->id])
            ->getJson('/api/user-batches');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($batch->id, $ids->toArray());
    }

    public function test_batch_created_in_non_active_tenant_is_visible_in_show(): void
    {
        $batch = $this->makeBatch($this->tenantB->id, $this->adminTenant->id);

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantA->id])
            ->getJson("/api/user-batches/{$batch->id}");

        $response->assertStatus(200)
            ->assertJsonPath('id', $batch->id);
    }

    public function test_root_sees_batch_with_null_tenant_id(): void
    {
        // Para root, tenant_id queda NULL (vista global). Con el scope
        // activo esto era el peor caso: NULL nunca matchea un IN(...).
        $root = User::factory()->root()->create(['status' => 'active']);
        $batch = $this->makeBatch(null, $root->id);

        $response = $this->actingAs($root)->getJson('/api/user-batches');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($batch->id, $ids->toArray());

        $showResponse = $this->actingAs($root)->getJson("/api/user-batches/{$batch->id}");
        $showResponse->assertStatus(200)->assertJsonPath('id', $batch->id);
    }

    public function test_admin_tenant_does_not_see_batch_of_unmanaged_tenant(): void
    {
        $tenantC = Tenant::factory()->create(['status' => 'active']);
        $otherAdmin = User::factory()
            ->admin()
            ->withTenantRole($tenantC, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $batch = $this->makeBatch($tenantC->id, $otherAdmin->id);

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantA->id])
            ->getJson('/api/user-batches');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertNotContains($batch->id, $ids->toArray());

        $showResponse = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantA->id])
            ->getJson("/api/user-batches/{$batch->id}");
        $showResponse->assertStatus(403);
    }
}
