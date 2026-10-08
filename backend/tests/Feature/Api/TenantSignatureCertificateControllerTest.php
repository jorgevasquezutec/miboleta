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

class TenantSignatureCertificateControllerTest extends TestCase
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

    private function upload(Tenant $tenant, $file, string $password = 'secret', ?User $as = null, array $extra = [])
    {
        return $this->actingAs($as ?? $this->root)->post(
            "/api/signature/tenants/{$tenant->id}/certificate",
            ['certificate' => $file, 'password' => $password] + $extra,
            ['Accept' => 'application/json']
        );
    }

    public function test_root_sube_certificado_que_coincide(): void
    {
        $r = $this->upload($this->tenant, $this->makePfx($this->defaultDn('20603839961'), 'secret', 400));

        $r->assertStatus(201)
            ->assertJsonPath('data.certificate_source', 'tenant')
            ->assertJsonPath('data.ruc_mismatch', false)
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.certificate.certificate_ruc', '20603839961');

        $path = TenantSignatureCertificate::where('tenant_id', $this->tenant->id)->value('certificate_path');
        Storage::disk('certificates')->assertExists($path);

        $log = AuditLog::where('action', 'signature.tenant_certificate_uploaded')->firstOrFail();
        $this->assertNull($log->tenant_id);
        $this->assertSame('Tenant', $log->entity_type);
        $this->assertSame($this->tenant->id, $log->entity_id);
        $this->assertSame($this->tenant->id, $log->metadata['tenant_id']);
    }

    public function test_ruc_distinto_no_se_rechaza(): void
    {
        $r = $this->upload($this->tenant, $this->makePfx($this->defaultDn('20100000001')));

        $r->assertStatus(201)
            ->assertJsonPath('data.ruc_mismatch', true)
            ->assertJsonPath('data.warnings.0.code', 'ruc_mismatch');
        $this->assertStringContainsString('no coincide', $r->json('message'));
    }

    public function test_certificado_sin_ruc_da_aviso(): void
    {
        $r = $this->upload($this->tenant, $this->makePfx([
            'countryName' => 'PE', 'organizationName' => 'ACME', 'commonName' => 'JUAN PEREZ',
        ]));

        $r->assertStatus(201);
        $this->assertContains('ruc_not_found', array_column($r->json('data.warnings'), 'code'));
    }

    public function test_reemplazo_borra_el_binario_anterior_y_deja_una_fila(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(201);
        $old = TenantSignatureCertificate::first()->certificate_path;

        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(201);

        $this->assertSame(1, TenantSignatureCertificate::count());
        Storage::disk('certificates')->assertMissing($old);
        Storage::disk('certificates')->assertExists(TenantSignatureCertificate::first()->certificate_path);
    }

    public function test_fallo_al_guardar_no_deja_binario_huerfano(): void
    {
        TenantSignatureCertificate::saving(function () {
            throw new \RuntimeException('boom');
        });

        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(500);

        $this->assertEmpty(Storage::disk('certificates')->allFiles());
    }

    public function test_validaciones(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn(), 'secret'), 'mala')
            ->assertStatus(422);
        $this->assertSame(0, TenantSignatureCertificate::count());
        $this->assertEmpty(Storage::disk('certificates')->allFiles());

        $this->actingAs($this->root)->post(
            "/api/signature/tenants/{$this->tenant->id}/certificate",
            ['certificate' => \Illuminate\Http\UploadedFile::fake()->create('c.txt', 5), 'password' => 'x'],
            ['Accept' => 'application/json']
        )->assertStatus(422)->assertJsonValidationErrors('certificate');

        $this->actingAs($this->root)->post(
            "/api/signature/tenants/{$this->tenant->id}/certificate",
            ['certificate' => $this->makePfx($this->defaultDn())],
            ['Accept' => 'application/json']
        )->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_guarda_la_tsa_de_la_empresa_y_la_expone(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()), 'secret', null, ['tsa_url' => 'https://freetsa.org/tsr'])
            ->assertStatus(201)
            ->assertJsonPath('data.certificate.tsa_url', 'https://freetsa.org/tsr');

        $this->assertSame('https://freetsa.org/tsr', TenantSignatureCertificate::first()->tsa_url);
        $this->actingAs($this->root)->getJson('/api/signature/tenants')
            ->assertJsonPath('data.0.certificate.tsa_url', 'https://freetsa.org/tsr');
        $log = AuditLog::where('action', 'signature.tenant_certificate_uploaded')->firstOrFail();
        $this->assertSame('https://freetsa.org/tsr', $log->metadata['tsa_url']);
    }

    public function test_sin_tsa_queda_null_y_el_reemplazo_la_limpia(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))
            ->assertStatus(201)->assertJsonPath('data.certificate.tsa_url', null);

        $this->upload($this->tenant, $this->makePfx($this->defaultDn()), 'secret', null, ['tsa_url' => 'https://freetsa.org/tsr'])->assertStatus(201);
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()), 'secret', null, ['tsa_url' => ''])
            ->assertStatus(201)->assertJsonPath('data.certificate.tsa_url', null);
        $this->assertNull(TenantSignatureCertificate::first()->tsa_url);
    }

    public function test_tsa_invalida_da_422_y_no_guarda_nada(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()), 'secret', null, ['tsa_url' => 'no-es-una-url'])
            ->assertStatus(422)->assertJsonValidationErrors('tsa_url');
        $this->assertSame(0, TenantSignatureCertificate::count());
        $this->assertEmpty(Storage::disk('certificates')->allFiles());
    }

    public function test_autorizacion(): void
    {
        $url = "/api/signature/tenants/{$this->tenant->id}/certificate";
        $client = User::factory()->withTenantRole($this->tenant, 'client', true)->create(['status' => 'active']);

        $this->actingAs($this->admin)->getJson('/api/signature/tenants')->assertStatus(403);
        $this->actingAs($this->admin)->deleteJson($url)->assertStatus(403);
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()), 'secret', $this->admin)->assertStatus(403);
        $this->actingAs($this->admin)->postJson($url, ['password' => ''])->assertStatus(403);
        $this->actingAs($client)->getJson('/api/signature/tenants')->assertStatus(403);

        $this->assertSame(0, TenantSignatureCertificate::count());
    }

    public function test_sin_autenticar_da_401(): void
    {
        $this->getJson('/api/signature/tenants')->assertStatus(401);
        $this->deleteJson("/api/signature/tenants/{$this->tenant->id}/certificate")->assertStatus(401);
    }

    public function test_admin_de_la_empresa_no_ve_la_auditoria_del_certificado(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(201);
        $this->actingAs($this->root)->deleteJson("/api/signature/tenants/{$this->tenant->id}/certificate")->assertStatus(200);

        $r = $this->actingAs($this->admin)->getJson('/api/reports/audit?per_page=100');

        $r->assertStatus(200);
        $actions = array_column($r->json('data'), 'action');
        $this->assertNotContains('signature.tenant_certificate_uploaded', $actions);
        $this->assertNotContains('signature.tenant_certificate_deleted', $actions);
    }

    public function test_empresa_inexistente_o_borrada_da_404(): void
    {
        $this->actingAs($this->root)->post('/api/signature/tenants/99999/certificate', [
            'certificate' => $this->makePfx($this->defaultDn()), 'password' => 'secret',
        ], ['Accept' => 'application/json'])->assertStatus(404);

        $this->actingAs($this->root)->deleteJson('/api/signature/tenants/99999/certificate')->assertStatus(404);

        $this->tenant->delete();
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(404);
    }

    public function test_listado_fuentes_filtros_y_meta(): void
    {
        $b = Tenant::factory()->create(['name' => 'Beta', 'ruc' => '20100000001']);
        $c = Tenant::factory()->create(['name' => 'Gamma', 'ruc' => '20100000002']);

        // Sin certificado global: todo 'none'.
        $r = $this->actingAs($this->root)->getJson('/api/signature/tenants')->assertStatus(200);
        $this->assertSame(['none'], array_values(array_unique(array_column($r->json('data'), 'certificate_source'))));
        $r->assertJsonPath('meta.global_has_certificate', false)
            ->assertJsonPath('data.0.warnings.0.code', 'no_certificate');

        SignatureSettings::current()->update(['certificate_path' => 'g.pfx']);

        // A (tenant) coincide; B con RUC distinto; C usa el global.
        $this->upload($this->tenant, $this->makePfx($this->defaultDn('20603839961'), 'secret', 400));
        $this->upload($b, $this->makePfx($this->defaultDn('20603839961'), 'secret', 400));

        $r = $this->actingAs($this->root)->getJson('/api/signature/tenants')->assertStatus(200);
        $by = collect($r->json('data'))->keyBy('tenant_name');
        $this->assertSame('tenant', $by['Overhead']['certificate_source']);
        $this->assertSame('tenant', $by['Beta']['certificate_source']);
        $this->assertSame('global', $by['Gamma']['certificate_source']);
        $r->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.with_own_certificate', 2)
            ->assertJsonPath('meta.with_warnings', 1)
            ->assertJsonPath('meta.global_has_certificate', true);

        $w = $this->actingAs($this->root)->getJson('/api/signature/tenants?only_warnings=1')->assertStatus(200);
        $this->assertCount(1, $w->json('data'));
        $this->assertSame('Beta', $w->json('data.0.tenant_name'));
        $w->assertJsonPath('meta.total', 1)->assertJsonPath('meta.with_own_certificate', 2);

        $this->assertCount(1, $this->actingAs($this->root)->getJson('/api/signature/tenants?search=Gam')->json('data'));
        $this->assertCount(1, $this->actingAs($this->root)->getJson('/api/signature/tenants?search=20100000001')->json('data'));
    }

    public function test_listado_paginacion(): void
    {
        foreach (range(1, 4) as $i) {
            Tenant::factory()->create(['name' => "Pag{$i}", 'ruc' => '2010000010'.$i]);
        }
        // 5 empresas en total, ninguna con certificado (todas con aviso).
        $get = fn (string $q) => $this->actingAs($this->root)->getJson('/api/signature/tenants'.$q)->assertStatus(200);

        $r = $get('?per_page=2&page=2');
        $this->assertCount(2, $r->json('data'));
        $r->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 2);

        $all = array_column($get('?per_page=100')->json('data'), 'tenant_id');
        $p3 = $get('?per_page=2&page=3');
        $this->assertCount(1, $p3->json('data'));
        $this->assertSame($all[4], $p3->json('data.0.tenant_id'));
        $this->assertSame(array_slice($all, 2, 2), array_column($r->json('data'), 'tenant_id'));

        // Pagina fuera de rango: data vacia, current_page = pedido.
        $o = $get('?per_page=2&page=9');
        $this->assertCount(0, $o->json('data'));
        $o->assertJsonPath('meta.current_page', 9)->assertJsonPath('meta.last_page', 3)->assertJsonPath('meta.total', 5);

        // Defaults y clamp.
        $get('')->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.current_page', 1);
        $get('?per_page=0')->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.last_page', 5);
        $get('?per_page=500')->assertJsonPath('meta.per_page', 100);
        $get('?per_page=abc&page=xyz')->assertJsonPath('meta.per_page', 10)->assertJsonPath('meta.current_page', 1);
        $get('?page=-3')->assertJsonPath('meta.current_page', 1);

        // total con only_warnings y conteos globales intactos.
        $this->upload($this->tenant, $this->makePfx($this->defaultDn('20603839961'), 'secret', 400));
        $w = $get('?only_warnings=1&per_page=2');
        $w->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.with_own_certificate', 1)
            ->assertJsonPath('meta.with_warnings', 4);
        $this->assertCount(2, $w->json('data'));
    }

    public function test_listado_fecha_exacta_y_sin_secretos(): void
    {
        $pfx = $this->makePfx($this->defaultDn(), 'secret', 400);
        $this->upload($this->tenant, $pfx)->assertStatus(201);

        $row = TenantSignatureCertificate::first();
        $r = $this->actingAs($this->root)->getJson('/api/signature/tenants')->assertStatus(200);

        // notAfter real leído directamente del .pfx subido (independiente de la BD).
        $this->assertTrue(openssl_pkcs12_read(file_get_contents($pfx->getRealPath()), $certs, 'secret'));
        $validTo = openssl_x509_parse($certs['cert'])['validTo_time_t'];
        $this->assertSame(
            gmdate('Y-m-d\TH:i:s', $validTo) . '.000000Z',
            $r->json('data.0.certificate.certificate_expires_at')
        );
        $raw = $r->getContent();
        $this->assertStringNotContainsString('certificate_password', $raw);
        $this->assertStringNotContainsString('certificate_path', $raw);
        $this->assertStringNotContainsString($row->certificate_path, $raw);
    }

    public function test_delete(): void
    {
        SignatureSettings::current()->update(['certificate_path' => 'g.pfx']);
        $this->upload($this->tenant, $this->makePfx($this->defaultDn()))->assertStatus(201);
        $path = TenantSignatureCertificate::first()->certificate_path;

        $this->actingAs($this->root)->deleteJson("/api/signature/tenants/{$this->tenant->id}/certificate")
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_source', 'global')
            ->assertJsonPath('data.has_own_certificate', false)
            ->assertJsonPath('data.certificate', null);

        Storage::disk('certificates')->assertMissing($path);
        $this->assertNull(AuditLog::where('action', 'signature.tenant_certificate_deleted')->firstOrFail()->tenant_id);

        $this->actingAs($this->root)->deleteJson("/api/signature/tenants/{$this->tenant->id}/certificate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'La empresa no tiene certificado propio.');
    }

    public function test_cambiar_el_ruc_de_la_empresa_cambia_el_mismatch(): void
    {
        $this->upload($this->tenant, $this->makePfx($this->defaultDn('20603839961')))->assertStatus(201);
        $this->actingAs($this->root)->getJson('/api/signature/tenants')->assertJsonPath('data.0.ruc_mismatch', false);

        $this->tenant->update(['ruc' => '20100000009']);

        $this->actingAs($this->root)->getJson('/api/signature/tenants')->assertJsonPath('data.0.ruc_mismatch', true);
    }

    public function test_settings_globales_incluyen_metadatos_tras_subir(): void
    {
        $this->actingAs($this->root)->post('/api/signature/certificate', [
            'certificate' => $this->makePfx($this->defaultDn('20603839961'), 'secret', 400),
            'password' => 'secret',
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->actingAs($this->root)->getJson('/api/signature/settings')
            ->assertStatus(200)
            ->assertJsonPath('data.certificate_ruc', '20603839961')
            ->assertJsonPath('data.certificate_organization', 'OVERHEAD MEN S.A.C.')
            ->assertJsonStructure(['data' => ['certificate_expires_at']]);
    }
}
