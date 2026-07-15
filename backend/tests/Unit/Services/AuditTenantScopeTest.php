<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para el fix de AuditService::log(): antes escribía
 * tenant_id = $user->primaryTenant()->id sin mirar el switcher, así que todo
 * lo que un usuario multi-empresa hiciera en la empresa B quedaba auditado
 * bajo la A (fuga entre empresas). Ahora usa ActiveTenantResolver (empresa
 * ACTIVA), con fallback a la primaria cuando no hay header (jobs/CLI).
 *
 * Se dispara la auditoría pegando a un endpoint real (PUT /api/profile, que
 * llama a AuditService::logProfileUpdated sin tenantId explícito) en vez de
 * llamar al servicio directo, para ejercer el flujo completo
 * TenantFilter -> _tenant_filter_ids -> ActiveTenantResolver.
 */
class AuditTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B', 'status' => 'active']);

        // Primaria en A, también en B.
        $this->ana = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin')
            ->create(['status' => 'active']);
    }

    public function test_audit_logs_under_active_tenant_not_primary(): void
    {
        // Ana tiene la empresa B activa en el switcher (no es su primaria).
        $response = $this->actingAs($this->ana)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->putJson('/api/profile', ['name' => 'Ana Actualizada']);

        $response->assertStatus(200);

        $log = AuditLog::where('action', AuditLog::ACTION_PROFILE_UPDATED)
            ->where('user_id', $this->ana->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(
            $this->tenantB->id,
            $log->tenant_id,
            'El log debe quedar bajo la empresa ACTIVA (B), no la primaria (A).'
        );
    }

    public function test_audit_falls_back_to_primary_tenant_without_header(): void
    {
        // Sin header (jobs/CLI/cliente antiguo): cae a la empresa primaria.
        $response = $this->actingAs($this->ana)
            ->putJson('/api/profile', ['name' => 'Ana Sin Header']);

        $response->assertStatus(200);

        $log = AuditLog::where('action', AuditLog::ACTION_PROFILE_UPDATED)
            ->where('user_id', $this->ana->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->tenantA->id, $log->tenant_id);
    }

    public function test_audit_for_root_has_null_tenant(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        $response = $this->actingAs($root)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->putJson('/api/profile', ['name' => 'Root Actualizado']);

        $response->assertStatus(200);

        $log = AuditLog::where('action', AuditLog::ACTION_PROFILE_UPDATED)
            ->where('user_id', $root->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->tenant_id, 'Root es de plataforma: no tiene empresa.');
    }
}
