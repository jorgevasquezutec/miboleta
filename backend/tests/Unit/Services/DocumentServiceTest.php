<?php

namespace Tests\Unit\Services;

use App\Exceptions\DocumentNotFoundException;
use App\Exceptions\UnauthorizedAccessException;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
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

        $this->documentService = new DocumentService(new AuditService());

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->docType = DocumentType::factory()->create();

        // withTenantRole asigna el rol DENTRO de la empresa (user_tenant_roles),
        // que es lo que se usa para autorizar. Los states client()/admin() solo
        // escriben el rol global (user_roles), que es un respaldo de display.
        $this->client = User::factory()
            ->client()
            ->withTenantRole($this->tenant, 'client', true)
            ->create(['status' => 'active']);

        $this->admin = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);

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

    /**
     * REGRESIÓN (fuga de privilegios entre empresas): un usuario multi-empresa
     * que es admin en una NO debe poder leer ni borrar documentos ajenos de otra
     * empresa donde solo es client.
     *
     * Antes del fix, canAccessDocument/deleteDocument resolvían el rol con
     * getCurrentRole() (respaldo global = unión de roles de todas sus empresas),
     * que devolvía 'admin' y, como el usuario sí pertenece a la empresa B,
     * concedía el acceso. Ahora el rol se resuelve dentro de la empresa DEL
     * DOCUMENTO.
     */
    public function test_admin_in_one_tenant_cannot_access_documents_of_tenant_where_is_only_client(): void
    {
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        // Admin en la empresa principal, pero solo client en la empresa B.
        // ->admin() reproduce el respaldo global (user_roles) que en producción
        // mantiene UserService::syncGlobalRoleFallback con la UNIÓN de los roles
        // de todas las empresas. Es imprescindible para que este test sea una
        // regresión real: es justo ese 'admin' global el que el código anterior
        // leía con getCurrentRole() para conceder el acceso indebido.
        $multiTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->withTenantRole($tenantB, 'client')
            ->create(['status' => 'active']);

        $colleagueInB = User::factory()->withTenantRole($tenantB, 'client', true)->create();

        // Documento AJENO dentro de la empresa B.
        $documentInB = Document::factory()->create([
            'user_id' => $colleagueInB->id,
            'tenant_id' => $tenantB->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $colleagueInB->id,
        ]);

        $this->assertFalse(
            $this->documentService->canAccessDocument($multiTenant, $documentInB),
            'FUGA: en la empresa B solo es client, no debe ver documentos ajenos '
            . 'aunque sea admin en otra empresa.'
        );

        $this->expectException(UnauthorizedAccessException::class);
        $this->documentService->deleteDocument($documentInB->id, $multiTenant);
    }

    /**
     * La contraparte: en la empresa donde SÍ es admin, el acceso funciona.
     * (Evita que el test de arriba pase por un deny-all accidental.)
     */
    public function test_multi_tenant_user_can_access_documents_where_actually_admin(): void
    {
        $tenantB = Tenant::factory()->create(['status' => 'active']);

        $multiTenant = User::factory()
            ->withTenantRole($this->tenant, 'admin', true)
            ->withTenantRole($tenantB, 'client')
            ->create(['status' => 'active']);

        $documentInPrimary = Document::factory()->create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'uploaded_by' => $this->admin->id,
        ]);

        $this->assertTrue(
            $this->documentService->canAccessDocument($multiTenant, $documentInPrimary),
            'Como admin en esta empresa sí debe poder ver documentos ajenos de ella.'
        );
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
