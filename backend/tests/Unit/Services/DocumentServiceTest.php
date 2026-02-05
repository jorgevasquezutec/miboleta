<?php

namespace Tests\Unit\Services;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentService $documentService;
    private User $client;
    private User $admin;
    private User $root;
    private Tenant $tenant;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->documentService = new DocumentService();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->docType = DocumentType::factory()->create();

        $this->client = User::factory()->client()->create(['status' => 'active']);
        $this->client->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->root = User::factory()->root()->create(['status' => 'active']);
    }

    public function test_client_can_only_see_own_documents(): void
    {
        Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $otherUser = User::factory()->client()->create();
        Document::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $documents = $this->documentService->getDocuments($this->client);

        $this->assertEquals(1, $documents->total());
        $this->assertEquals($this->client->id, $documents->first()->user_id);
    }

    public function test_admin_can_see_all_tenant_documents(): void
    {
        Document::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $documents = $this->documentService->getDocuments($this->admin);

        $this->assertEquals(3, $documents->total());
    }

    public function test_root_can_see_all_documents(): void
    {
        $tenant2 = Tenant::factory()->create();

        Document::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->count(2)->create([
            'tenant_id' => $tenant2->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $documents = $this->documentService->getDocuments($this->root);

        $this->assertEquals(4, $documents->total());
    }

    public function test_get_document_throws_not_found_exception(): void
    {
        $this->expectException(DocumentNotFoundException::class);

        $this->documentService->getDocument(999999, $this->client);
    }

    public function test_client_cannot_access_other_users_document(): void
    {
        $otherUser = User::factory()->client()->create();
        $document = Document::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $this->expectException(UnauthorizedAccessException::class);

        $this->documentService->getDocument($document->id, $this->client);
    }

    public function test_can_access_document_returns_true_for_owner(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $canAccess = $this->documentService->canAccessDocument($this->client, $document);

        $this->assertTrue($canAccess);
    }

    public function test_can_access_document_returns_true_for_root(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $canAccess = $this->documentService->canAccessDocument($this->root, $document);

        $this->assertTrue($canAccess);
    }

    public function test_can_access_document_returns_true_for_admin_in_same_tenant(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $canAccess = $this->documentService->canAccessDocument($this->admin, $document);

        $this->assertTrue($canAccess);
    }

    public function test_can_access_document_returns_false_for_admin_in_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAdmin = User::factory()->admin()->create();
        $otherAdmin->tenants()->attach($otherTenant->id);

        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $canAccess = $this->documentService->canAccessDocument($otherAdmin, $document);

        $this->assertFalse($canAccess);
    }

    public function test_filter_documents_by_status(): void
    {
        Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'status' => 'pending',
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->signed()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $pendingDocs = $this->documentService->getDocuments($this->client, ['status' => 'pending']);
        $signedDocs = $this->documentService->getDocuments($this->client, ['status' => 'signed']);

        $this->assertEquals(1, $pendingDocs->total());
        $this->assertEquals(1, $signedDocs->total());
    }

    public function test_filter_documents_by_type(): void
    {
        $docType2 = DocumentType::factory()->create();

        Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $docType2->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $documents = $this->documentService->getDocuments($this->client, [
            'doc_type_id' => $docType2->id,
        ]);

        $this->assertEquals(1, $documents->total());
        $this->assertEquals($docType2->id, $documents->first()->doc_type_id);
    }

    public function test_client_cannot_delete_documents(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $this->expectException(UnauthorizedAccessException::class);

        $this->documentService->deleteDocument($document->id, $this->client);
    }

    public function test_admin_can_delete_document(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $result = $this->documentService->deleteDocument($document->id, $this->admin);

        $this->assertTrue($result);
        // Uses soft delete
        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    public function test_get_orphan_documents(): void
    {
        // Orphan documents have status = 'orphan'
        Document::factory()->create([
            'user_id' => null,
            'status' => 'orphan',
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        Document::factory()->create([
            'user_id' => $this->client->id,
            'status' => 'pending',
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $orphans = $this->documentService->getOrphanDocuments($this->admin);

        $this->assertEquals(1, $orphans->total());
        $this->assertEquals('orphan', $orphans->first()->status);
    }

    public function test_assign_orphan_document_to_user(): void
    {
        $orphan = Document::factory()->create([
            'user_id' => null,
            'status' => 'orphan',
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $result = $this->documentService->assignOrphanDocument($orphan, $this->client);

        $this->assertEquals($this->client->id, $result->user_id);
    }

    public function test_cannot_assign_non_orphan_document(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->documentService->assignOrphanDocument($document, $this->admin);
    }
}
