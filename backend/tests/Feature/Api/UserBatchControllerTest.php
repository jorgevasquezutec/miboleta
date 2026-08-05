<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Observación 5 (historial de cargas masivas): UserBatchController::index()
 * usaba igualdad exacta contra 'status', así que los chips "Completados"/
 * "En Proceso"/"Fallidos" del historial dejaban invisibles los batches en
 * 'partial' y 'pending' -solo aparecían bajo "Todos"-. Se prueba aquí el
 * mapeo de cada chip: 'completed' agrupa completed+partial (mismo criterio
 * que UserBatch::scopeCompleted/isCompleted), 'processing' agrupa
 * pending+processing, 'failed' se mantiene igualdad exacta.
 */
class UserBatchControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->adminTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
    }

    private function makeBatch(string $status, array $overrides = []): UserBatch
    {
        return UserBatch::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $this->adminTenant->id,
            'original_filename' => 'usuarios.xlsx',
            'file_size' => 100,
            'status' => $status,
            'total_rows' => 5,
            'created_users' => $status === 'failed' ? 0 : 5,
        ], $overrides));
    }

    private function idsFrom(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('id')->toArray();
    }

    public function test_filter_completed_includes_partial_batches(): void
    {
        $completed = $this->makeBatch('completed');
        $partial = $this->makeBatch('partial');
        $failed = $this->makeBatch('failed');
        $pending = $this->makeBatch('pending');
        $processing = $this->makeBatch('processing');

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches?status=completed');

        $response->assertStatus(200);
        $ids = $this->idsFrom($response);

        $this->assertContains($completed->id, $ids);
        $this->assertContains($partial->id, $ids);
        $this->assertNotContains($failed->id, $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($processing->id, $ids);
    }

    public function test_filter_processing_includes_pending_batches(): void
    {
        $pending = $this->makeBatch('pending');
        $processing = $this->makeBatch('processing');
        $completed = $this->makeBatch('completed');
        $partial = $this->makeBatch('partial');
        $failed = $this->makeBatch('failed');

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches?status=processing');

        $response->assertStatus(200);
        $ids = $this->idsFrom($response);

        $this->assertContains($pending->id, $ids);
        $this->assertContains($processing->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($partial->id, $ids);
        $this->assertNotContains($failed->id, $ids);
    }

    public function test_filter_failed_only_includes_failed_batches(): void
    {
        $failed = $this->makeBatch('failed');
        $completed = $this->makeBatch('completed');
        $partial = $this->makeBatch('partial');
        $pending = $this->makeBatch('pending');
        $processing = $this->makeBatch('processing');

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches?status=failed');

        $response->assertStatus(200);
        $ids = $this->idsFrom($response);

        $this->assertContains($failed->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($partial->id, $ids);
        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($processing->id, $ids);
    }

    public function test_no_filter_returns_all_statuses(): void
    {
        $batches = [
            $this->makeBatch('completed'),
            $this->makeBatch('partial'),
            $this->makeBatch('failed'),
            $this->makeBatch('pending'),
            $this->makeBatch('processing'),
        ];

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches');

        $response->assertStatus(200);
        $ids = $this->idsFrom($response);

        foreach ($batches as $batch) {
            $this->assertContains($batch->id, $ids);
        }
    }
}
