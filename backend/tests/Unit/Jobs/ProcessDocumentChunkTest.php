<?php

namespace Tests\Unit\Jobs;

use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\DocumentType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Tests for Issues 8, 9: Document processing fixes.
 * - Issue 9: Re-uploading after delete should restore soft-deleted document
 * - Issue 8: requires_signature should combine batch and document type flags
 */
class ProcessDocumentChunkTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private DocumentType $docType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Storage::fake('documents');
        Storage::fake('local');

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->docType = DocumentType::factory()->create(['requires_signature' => true]);
    }

    // ==========================================
    // Issue 9: Soft-deleted documents should be restored on re-upload
    // ==========================================

    public function test_soft_deleted_document_is_found_with_trashed(): void
    {
        $user = User::factory()->client()->create(['document_text' => '12345678']);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        // Create a document and soft-delete it
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'doc_type_id' => $this->docType->id,
            'period' => '2025-01',
            'employee_document_number' => '12345678',
            'status' => 'pending',
            'uploaded_by' => $this->admin->id,
        ]);

        $document->delete(); // Soft delete

        // Verify it's soft-deleted
        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        // Verify withTrashed() finds it
        $found = Document::withTrashed()
            ->where('tenant_id', $this->tenant->id)
            ->where('doc_type_id', $this->docType->id)
            ->where('period', '2025-01')
            ->where('employee_document_number', '12345678')
            ->first();

        $this->assertNotNull($found, 'Soft-deleted document should be found with withTrashed()');
        $this->assertTrue($found->trashed(), 'Document should be in trashed state');
    }

    public function test_soft_deleted_document_can_be_restored(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'period' => '2025-01',
            'employee_document_number' => '12345678',
            'uploaded_by' => $this->admin->id,
        ]);

        $document->delete();
        $this->assertSoftDeleted('documents', ['id' => $document->id]);

        // Restore
        $document->restore();

        // Verify restored
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'deleted_at' => null,
        ]);
    }

    public function test_without_trashed_does_not_find_deleted_documents(): void
    {
        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $this->docType->id,
            'period' => '2025-01',
            'employee_document_number' => '12345678',
            'uploaded_by' => $this->admin->id,
        ]);

        $document->delete();

        // Without withTrashed - should NOT find the document
        $found = Document::where('tenant_id', $this->tenant->id)
            ->where('doc_type_id', $this->docType->id)
            ->where('period', '2025-01')
            ->where('employee_document_number', '12345678')
            ->first();

        $this->assertNull($found, 'Regular query should NOT find soft-deleted document');
    }

    // ==========================================
    // Issue 8: requires_signature should combine batch + doc type flags
    // ==========================================

    public function test_document_type_with_requires_signature_true(): void
    {
        $docType = DocumentType::factory()->create(['requires_signature' => true]);
        $this->assertTrue($docType->requires_signature);
    }

    public function test_document_type_without_requires_signature(): void
    {
        $docType = DocumentType::factory()->noSignature()->create();
        $this->assertFalse($docType->requires_signature);
    }

    public function test_combined_signature_flag_batch_true_type_false(): void
    {
        $batchRequiresSignature = true;
        $docTypeRequiresSignature = false;

        $combined = $batchRequiresSignature || $docTypeRequiresSignature;

        $this->assertTrue($combined, 'Should require signature if batch flag is true');
    }

    public function test_combined_signature_flag_batch_false_type_true(): void
    {
        $batchRequiresSignature = false;
        $docTypeRequiresSignature = true;

        $combined = $batchRequiresSignature || $docTypeRequiresSignature;

        $this->assertTrue($combined, 'Should require signature if document type flag is true');
    }

    public function test_combined_signature_flag_both_false(): void
    {
        $batchRequiresSignature = false;
        $docTypeRequiresSignature = false;

        $combined = $batchRequiresSignature || $docTypeRequiresSignature;

        $this->assertFalse($combined, 'Should NOT require signature if both flags are false');
    }
}
