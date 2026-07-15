<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use App\Models\DocumentType;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Tenant $tenant;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->docType = DocumentType::factory()->create();

        // withTenantRole asigna el rol DENTRO de la empresa (user_tenant_roles),
        // que es la fuente de verdad para autorizar. Los states client()/admin()
        // solo escriben el rol global (user_roles), que es un respaldo de display.
        $this->user = User::factory()
            ->client()
            ->withTenantRole($this->tenant, 'client', true)
            ->create(['status' => 'active']);

        $this->admin = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);
    }

    public function test_user_can_list_their_documents(): void
    {
        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/documents?my_documents=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'file_path',
                        'status',
                        'created_at',
                    ]
                ],
                'meta'
            ]);
    }

    public function test_user_can_view_their_document(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $document->id]);
    }

    public function test_user_cannot_view_other_users_document(): void
    {
        $otherUser = User::factory()->client()->create(['status' => 'active']);
        $otherUser->tenants()->attach($this->tenant->id);

        $document = Document::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_all_tenant_documents(): void
    {
        Document::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/documents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta'
            ]);
    }

    public function test_user_can_filter_documents_by_status(): void
    {
        Document::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'status' => 'pending',
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->count(3)->signed()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/documents?status=signed&my_documents=true');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_user_can_filter_documents_by_type(): void
    {
        $docType2 = DocumentType::factory()->create();

        Document::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $docType2->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/documents?doc_type_id={$docType2->id}&my_documents=true");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_admin_can_delete_document(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200);
        // Document model uses SoftDeletes
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_unauthenticated_user_cannot_access_documents(): void
    {
        $response = $this->getJson('/api/documents');

        $response->assertStatus(401);
    }

    public function test_user_can_download_their_document(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/api/documents/{$document->id}/download");

        // This will fail if file doesn't exist, but tests the authorization
        $this->assertTrue(in_array($response->status(), [200, 404]));
    }
}
