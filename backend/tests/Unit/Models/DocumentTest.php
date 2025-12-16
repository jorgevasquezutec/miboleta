<?php

namespace Tests\Unit\Models;

use App\Models\Document;
use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $document = Document::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertEquals($user->id, $document->user->id);
    }

    public function test_document_belongs_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals($tenant->id, $document->tenant->id);
    }

    public function test_document_has_correct_default_status(): void
    {
        $document = Document::factory()->create();

        $this->assertEquals('pending', $document->status);
    }

    public function test_document_can_be_signed(): void
    {
        $document = Document::factory()->create(['status' => 'pending']);

        $document->sign([
            'ip' => '127.0.0.1',
            'user_agent' => 'Test',
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->assertEquals('signed', $document->status);
        $this->assertNotNull($document->signed_at);
        $this->assertNotNull($document->signature);
    }

    public function test_document_is_signed_method(): void
    {
        $pendingDoc = Document::factory()->create(['status' => 'pending']);
        $signedDoc = Document::factory()->signed()->create();

        $this->assertFalse($pendingDoc->isSigned());
        $this->assertTrue($signedDoc->isSigned());
    }

    public function test_document_can_be_orphan(): void
    {
        $document = Document::factory()->orphan()->create();

        $this->assertEquals('orphan', $document->status);
        $this->assertNull($document->user_id);
        $this->assertTrue($document->isOrphan());
    }

    public function test_document_needs_signature(): void
    {
        $docNeedsSignature = Document::factory()->create([
            'requires_signature' => true,
            'status' => 'pending',
        ]);

        $docNoSignature = Document::factory()->noSignature()->create([
            'status' => 'pending',
        ]);

        $signedDoc = Document::factory()->signed()->create();

        $this->assertTrue($docNeedsSignature->needsSignature());
        $this->assertFalse($docNoSignature->needsSignature());
        $this->assertFalse($signedDoc->needsSignature());
    }

    public function test_document_file_size_formatted(): void
    {
        $docBytes = Document::factory()->create(['file_size' => 500]);
        $docKb = Document::factory()->create(['file_size' => 10240]);
        $docMb = Document::factory()->create(['file_size' => 2097152]);

        $this->assertEquals('500 bytes', $docBytes->fileSizeFormatted);
        $this->assertStringContainsString('KB', $docKb->fileSizeFormatted);
        $this->assertStringContainsString('MB', $docMb->fileSizeFormatted);
    }

    public function test_document_can_be_assigned_to_user(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->orphan()->create();

        $document->assignToUser($user);

        $this->assertEquals($user->id, $document->user_id);
        $this->assertEquals('pending', $document->status);
    }
}
