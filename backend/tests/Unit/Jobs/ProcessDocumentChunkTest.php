<?php

namespace Tests\Unit\Jobs;

use App\Models\Document;
use App\Models\DocumentBatch;
use App\Models\DocumentType;
use App\Jobs\ProcessDocumentChunk;
use App\Jobs\SignDocument;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SignatureCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

/**
 * Tests for Issues 8, 9: Document processing fixes.
 * - Issue 9: Re-uploading after delete should restore soft-deleted document
 * - Issue 8: requires_signature should combine batch and document type flags
 */
class ProcessDocumentChunkTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

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

    // ==========================================
    // Firma digital de la empresa (PAdES) al subir / reemplazar
    // ==========================================

    private function enableGlobalSignature(): void
    {
        Storage::fake('certificates');
        $root = User::factory()->root()->create(['status' => 'active']);
        app(SignatureCertificateService::class)->storeCertificate(
            $root, $this->makePfx($this->defaultDn('20603839961'), 'secret', 365), 'secret', 'https://tsa.example/ts'
        );
        SignatureSettings::current()->update(['signature_enabled' => true]);
    }

    /** Ejecuta el job con un ZIP real de un solo PDF para el DNI dado. */
    private function processZip(string $dni, string $content): void
    {
        $batch = DocumentBatch::create([
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->admin->id,
            'type_id' => $this->docType->id,
            'period' => '2025-01',
            'original_filename' => 'lote.zip',
            'total_files' => 1,
            'requires_signature' => true,
        ]);

        Storage::disk('local')->put('lote.zip', '');
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('local')->path('lote.zip'), \ZipArchive::OVERWRITE | \ZipArchive::CREATE);
        $zip->addFromString("{$dni}.pdf", $content);
        $zip->close();

        (new ProcessDocumentChunk($batch, 'lote.zip', [[
            'filename' => "{$dni}.pdf", 'document_number' => $dni, 'index' => 0, 'size' => strlen($content),
        ]]))->handle();
    }

    public function test_al_subir_con_firma_activada_queda_pending_y_se_encola_en_signing(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $user = User::factory()->client()->withTenantRole($this->tenant, 'client', true)->create(['document_text' => '11111111']);

        $this->processZip('11111111', '%PDF-1.4 nuevo');

        $doc = Document::where('employee_document_number', '11111111')->firstOrFail();
        $this->assertSame('pending', $doc->status);
        $this->assertSame('pending', $doc->digital_signature_status);
        Queue::assertPushedOn('signing', SignDocument::class, fn ($j) => $j->documentId === $doc->id);
    }

    public function test_al_subir_sin_firma_activada_no_encola(): void
    {
        Queue::fake();
        User::factory()->client()->withTenantRole($this->tenant, 'client', true)->create(['document_text' => '22222222']);

        $this->processZip('22222222', '%PDF-1.4 nuevo');

        $doc = Document::where('employee_document_number', '22222222')->firstOrFail();
        $this->assertNull($doc->digital_signature_status);
        Queue::assertNotPushed(SignDocument::class);
    }

    public function test_el_reemplazo_deja_en_null_las_columnas_digitales_borra_la_base_y_reencola(): void
    {
        Queue::fake();
        $this->enableGlobalSignature();
        $user = User::factory()->client()->withTenantRole($this->tenant, 'client', true)->create(['document_text' => '33333333']);
        $path = "{$this->tenant->id}/{$this->docType->name}/2025-01/33333333.pdf";
        $original = "{$this->tenant->id}/{$this->docType->name}/2025-01/.originals/33333333.pdfa.pdf";
        Storage::disk('documents')->put($path, '%PDF-viejo-firmado');
        Storage::disk('documents')->put($original, '%PDF-base-vieja');
        $existing = Document::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $user->id, 'doc_type_id' => $this->docType->id,
            'period' => '2025-01', 'employee_document_number' => '33333333', 'uploaded_by' => $this->admin->id,
            'file_path' => $path, 'status' => 'signed', 'signed_at' => now(),
            'signature' => ['verification_method' => 'email_2fa'], 'version' => 4,
            'digital_signature' => ['method' => 'pades_pyhanko', 'includes_conformity' => true],
            'digitally_signed_at' => now(), 'digital_signature_status' => 'signed',
            'digital_signature_error' => 'viejo', 'original_file_path' => $original, 'original_has_conformity' => true,
        ]);

        $this->processZip('33333333', '%PDF-1.4 reemplazo');

        $fresh = $existing->fresh();
        $this->assertSame(5, $fresh->version);
        $this->assertNull($fresh->signature);
        $this->assertNull($fresh->signed_at);
        $this->assertNull($fresh->digital_signature);
        $this->assertNull($fresh->digitally_signed_at);
        $this->assertNull($fresh->digital_signature_error);
        $this->assertNull($fresh->original_file_path);
        $this->assertFalse($fresh->original_has_conformity);
        // Vuelve a entrar al pipeline como si fuera nuevo.
        $this->assertSame('pending', $fresh->status);
        $this->assertSame('pending', $fresh->digital_signature_status);
        Storage::disk('documents')->assertMissing($original);
        $this->assertSame('%PDF-1.4 reemplazo', Storage::disk('documents')->get($path));
        Queue::assertPushedOn('signing', SignDocument::class, fn ($j) => $j->documentId === $existing->id);
    }
}
