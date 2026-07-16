<?php

namespace Tests\Feature\Api;

use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Tests for Issue 4: Upload batch requires tenant_id.
 * Verifies that the UploadZipBatchRequest validates tenant_id is present.
 */
class UploadBatchValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->docType = DocumentType::factory()->create();

        // withTenantRole asigna el rol DENTRO de la empresa (user_tenant_roles),
        // que es lo que usa authorize() para resolver el rol; admin() solo
        // escribe el rol global (user_roles), un respaldo de display.
        $this->admin = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);
    }

    public function test_upload_requires_tenant_id(): void
    {
        $file = UploadedFile::fake()->create('documents.zip', 1024, 'application/zip');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/document-batches/upload', [
                'file' => $file,
                'type_id' => $this->docType->id,
                'period' => '2025-01',
                // Missing tenant_id
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_upload_rejects_invalid_tenant_id(): void
    {
        $file = UploadedFile::fake()->create('documents.zip', 1024, 'application/zip');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/document-batches/upload', [
                'file' => $file,
                'type_id' => $this->docType->id,
                'period' => '2025-01',
                'tenant_id' => 99999, // Non-existent tenant
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_id']);
    }

    public function test_upload_accepts_valid_tenant_id(): void
    {
        $file = UploadedFile::fake()->create('documents.zip', 1024, 'application/zip');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/document-batches/upload', [
                'file' => $file,
                'type_id' => $this->docType->id,
                'period' => '2025-01',
                'tenant_id' => $this->tenant->id,
            ]);

        // Should not have tenant_id validation error
        // (might fail for other reasons like ZIP content, but tenant_id should pass)
        $this->assertNotEquals(422, $response->status(), 'Should not fail validation with valid tenant_id');
    }

    public function test_upload_requires_file(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/document-batches/upload', [
                'type_id' => $this->docType->id,
                'period' => '2025-01',
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_upload_requires_type_id(): void
    {
        $file = UploadedFile::fake()->create('documents.zip', 1024, 'application/zip');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/document-batches/upload', [
                'file' => $file,
                'period' => '2025-01',
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type_id']);
    }

    public function test_client_cannot_upload_batch(): void
    {
        $client = User::factory()->client()->create(['status' => 'active']);
        $client->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $file = UploadedFile::fake()->create('documents.zip', 1024, 'application/zip');

        $response = $this->actingAs($client)
            ->postJson('/api/document-batches/upload', [
                'file' => $file,
                'type_id' => $this->docType->id,
                'period' => '2025-01',
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(403);
    }
}
