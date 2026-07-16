<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\DocumentBatch;
use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentBatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cubre la autorización por empresa de los lotes de carga. Antes no había
 * ningún test de este servicio, y su check era `getCurrentRole() !== 'client'`
 * (rol global, por descarte).
 */
class DocumentBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentBatchService $service;
    private Tenant $tenantA;
    private Tenant $tenantB;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->service = app(DocumentBatchService::class);

        $this->tenantA = Tenant::factory()->create(['status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['status' => 'active']);
        $this->docType = DocumentType::factory()->create();
    }

    private function makeBatch(Tenant $tenant, User $uploader): DocumentBatch
    {
        return DocumentBatch::create([
            'tenant_id' => $tenant->id,
            'uploaded_by' => $uploader->id,
            'type_id' => $this->docType->id,
            'period' => '2026-07',
            'original_filename' => 'lote.zip',
            'total_files' => 1,
        ]);
    }

    /**
     * REGRESIÓN (fuga entre empresas): admin en la empresa A, client en la B.
     * No debe poder abrir un lote de la empresa B.
     *
     * Antes el check era `getCurrentRole() !== 'client'`, y el respaldo global
     * (user_roles, que en producción mantiene syncGlobalRoleFallback con la
     * UNIÓN de roles) resolvía 'admin' ⇒ concedía el acceso al lote de B.
     */
    public function test_admin_in_one_tenant_cannot_open_batch_of_tenant_where_is_only_client(): void
    {
        $multiTenant = User::factory()
            ->admin() // respaldo global (simula syncGlobalRoleFallback)
            ->withTenantRole($this->tenantA, 'admin', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $uploaderB = User::factory()->withTenantRole($this->tenantB, 'admin', true)->create();
        $batchInB = $this->makeBatch($this->tenantB, $uploaderB);

        $this->expectException(UnauthorizedAccessException::class);
        $this->service->getBatch($batchInB->id, $multiTenant);
    }

    public function test_admin_can_open_batch_of_own_tenant(): void
    {
        $admin = User::factory()->withTenantRole($this->tenantA, 'admin', true)->create();
        $batch = $this->makeBatch($this->tenantA, $admin);

        $this->assertSame($batch->id, $this->service->getBatch($batch->id, $admin)->id);
    }

    public function test_client_cannot_open_batches(): void
    {
        $client = User::factory()->withTenantRole($this->tenantA, 'client', true)->create();
        $uploader = User::factory()->withTenantRole($this->tenantA, 'admin', true)->create();
        $batch = $this->makeBatch($this->tenantA, $uploader);

        $this->expectException(UnauthorizedAccessException::class);
        $this->service->getBatch($batch->id, $client);
    }

    public function test_can_access_batches_follows_the_access_matrix(): void
    {
        // Matriz: 'documents.view_batches' = [admin, admin_tenant].
        $admin = User::factory()->withTenantRole($this->tenantA, 'admin', true)->create();
        $adminTenant = User::factory()->withTenantRole($this->tenantA, 'admin_tenant', true)->create();
        $client = User::factory()->withTenantRole($this->tenantA, 'client', true)->create();
        $aprobador = User::factory()->withTenantRole($this->tenantA, 'aprobador', true)->create();
        $root = User::factory()->root()->create();

        $this->assertTrue($this->service->canAccessBatches($admin, $this->tenantA->id));
        $this->assertTrue($this->service->canAccessBatches($adminTenant, $this->tenantA->id));
        $this->assertFalse($this->service->canAccessBatches($client, $this->tenantA->id));
        $this->assertFalse($this->service->canAccessBatches($aprobador, $this->tenantA->id));
        // Root NO ve lotes según la matriz ("Ver lotes de carga" = '-' para root).
        $this->assertFalse($this->service->canAccessBatches($root, $this->tenantA->id));

        // Y en la empresa donde no tiene rol, tampoco.
        $this->assertFalse($this->service->canAccessBatches($admin, $this->tenantB->id));
    }
}
