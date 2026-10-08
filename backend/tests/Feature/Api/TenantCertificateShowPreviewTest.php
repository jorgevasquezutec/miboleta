<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\SignatureSettings;
use App\Models\Tenant;
use App\Models\TenantSignatureCertificate;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MakesTestCertificates;
use Tests\TestCase;

class TenantCertificateShowPreviewTest extends TestCase
{
    use MakesTestCertificates, RefreshDatabase;

    private User $root;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('certificates');

        $this->tenant = Tenant::factory()->create(['name' => 'Overhead', 'ruc' => '20603839961']);
        $this->root = User::factory()->root()->create(['status' => 'active']);
        $this->admin = User::factory()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
    }

    private function preview($file, string $password = 'secret', ?string $ruc = null, ?User $as = null)
    {
        $payload = ['certificate' => $file, 'password' => $password];
        if ($ruc !== null) {
            $payload['ruc'] = $ruc;
        }

        return $this->actingAs($as ?? $this->root)->post('/api/signature/certificate/preview', $payload, ['Accept' => 'application/json']);
    }

    private function url(int $id): string
    {
        return "/api/signature/tenants/{$id}/certificate";
    }

    public function test_show_item_none_global_y_tenant(): void
    {
        $this->actingAs($this->root)->getJson($this->url($this->tenant->id))
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_source', 'none')
            ->assertJsonPath('data.warnings.0.code', 'no_certificate');

        SignatureSettings::current()->update(['certificate_path' => 'g.pfx']);
        $this->actingAs($this->root)->getJson($this->url($this->tenant->id))
            ->assertJsonPath('data.certificate_source', 'global')
            ->assertJsonPath('data.certificate', null);

        $this->actingAs($this->root)->post($this->url($this->tenant->id), [
            'certificate' => $this->makePfx($this->defaultDn(), 'secret', 400), 'password' => 'secret',
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $r = $this->actingAs($this->root)->getJson($this->url($this->tenant->id));
        $r->assertStatus(200)
            ->assertJsonPath('data.certificate_source', 'tenant')
            ->assertJsonPath('data.has_own_certificate', true)
            ->assertJsonPath('data.tenant_id', $this->tenant->id);
        $row = TenantSignatureCertificate::first();
        $this->assertStringNotContainsString('certificate_password', $r->getContent());
        $this->assertStringNotContainsString('certificate_path', $r->getContent());
        $this->assertStringNotContainsString($row->certificate_path, $r->getContent());
    }

    public function test_show_403_admin_y_404(): void
    {
        $this->actingAs($this->admin)->getJson($this->url($this->tenant->id))->assertStatus(403);
        $this->actingAs($this->admin)->getJson($this->url(99999))->assertStatus(403);
        $this->actingAs($this->root)->getJson($this->url(99999))
            ->assertStatus(404)->assertJsonPath('message', 'Empresa no encontrada.');

        $this->tenant->delete();
        $this->actingAs($this->root)->getJson($this->url($this->tenant->id))->assertStatus(404);
    }

    public function test_preview_coincide(): void
    {
        $this->preview($this->makePfx($this->defaultDn('20603839961'), 'secret', 400), 'secret', '20.603.839.961')
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_ruc', '20603839961')
            ->assertJsonPath('data.certificate_organization', 'OVERHEAD MEN S.A.C.')
            ->assertJsonPath('data.ruc_mismatch', false)
            ->assertJsonPath('data.warnings', [])
            ->assertJsonStructure(['data' => ['certificate_subject', 'certificate_expires_at']]);
    }

    public function test_preview_distinto_da_mismatch_y_warning(): void
    {
        $r = $this->preview($this->makePfx($this->defaultDn('20100000001'), 'secret', 400), 'secret', '20603839961');

        $r->assertStatus(200)
            ->assertJsonPath('data.ruc_mismatch', true)
            ->assertJsonPath('data.warnings.0.code', 'ruc_mismatch');
        $this->assertStringContainsString('no coincide', $r->json('data.warnings.0.message'));
    }

    public function test_preview_sin_ruc_no_hay_mismatch(): void
    {
        $this->preview($this->makePfx($this->defaultDn('20100000001'), 'secret', 400))
            ->assertStatus(200)
            ->assertJsonPath('data.ruc_mismatch', false)
            ->assertJsonPath('data.warnings', []);
    }

    public function test_preview_password_erronea_y_extension_invalida(): void
    {
        $this->preview($this->makePfx($this->defaultDn()), 'mala')
            ->assertStatus(422);
        $this->preview(\Illuminate\Http\UploadedFile::fake()->create('x.txt', 1))
            ->assertStatus(422);
    }

    public function test_preview_admin_403(): void
    {
        $this->preview($this->makePfx($this->defaultDn()), 'secret', null, $this->admin)->assertStatus(403);
        $this->preview(\Illuminate\Http\UploadedFile::fake()->create('x.txt', 1), 'x', null, $this->admin)->assertStatus(403);
    }

    public function test_preview_no_guarda_nada(): void
    {
        $this->preview($this->makePfx($this->defaultDn()), 'secret', '20603839961')->assertStatus(200);

        $this->assertSame([], Storage::disk('certificates')->allFiles());
        $this->assertSame(0, TenantSignatureCertificate::count());
        $this->assertSame(0, AuditLog::where('action', 'like', 'signature.%')->count());
    }
}
