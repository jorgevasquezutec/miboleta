<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\PlatformSettings;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PlatformSettingsService;
use App\Services\TenantMailerService;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica que las operaciones sensibles generen registros de auditoría y,
 * sobre todo, que NUNCA se filtren contraseñas (SMTP u otras) en los valores
 * old/new/metadata de un AuditLog.
 */
class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    private TenantService $tenantService;
    private PlatformSettingsService $platformSettingsService;
    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $audit = new AuditService();
        $this->tenantService = new TenantService(new TenantMailerService(), $audit);
        $this->platformSettingsService = new PlatformSettingsService($audit);

        $this->root = User::factory()->root()->create(['status' => 'active']);
    }

    public function test_tenant_create_update_delete_are_audited(): void
    {
        $tenant = $this->tenantService->createTenant([
            'name' => 'AuditCo',
            'ruc' => '20999888777',
            'business_name' => 'Audit Co SAC',
            'mail_host' => 'smtp.audit.com',
            'mail_from_address' => 'no-reply@audit.com',
            'mail_password' => 'SUPER_SECRET_SMTP',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_TENANT_CREATED,
            'entity_type' => 'Tenant',
            'entity_id' => $tenant->id,
        ]);

        $this->tenantService->updateTenant($tenant, ['phone' => '999']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_TENANT_UPDATED,
            'entity_id' => $tenant->id,
        ]);

        $this->tenantService->deleteTenant($tenant->id, $this->root);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_TENANT_DELETED,
            'entity_id' => $tenant->id,
        ]);
    }

    public function test_tenant_audit_never_stores_smtp_password(): void
    {
        $tenant = $this->tenantService->createTenant([
            'name' => 'SecretCo',
            'ruc' => '20111222333',
            'business_name' => 'Secret Co SAC',
            'mail_host' => 'smtp.secret.com',
            'mail_from_address' => 'no-reply@secret.com',
            'mail_password' => 'SUPER_SECRET_SMTP',
        ]);

        $log = AuditLog::where('action', AuditLog::ACTION_TENANT_CREATED)
            ->where('entity_id', $tenant->id)
            ->firstOrFail();

        $blob = json_encode([$log->old_values, $log->new_values, $log->metadata]);
        $this->assertStringNotContainsString('SUPER_SECRET_SMTP', $blob);
        // El snapshot expone solo el booleano, nunca la contraseña.
        $this->assertTrue($log->new_values['has_mail_password']);
        $this->assertArrayNotHasKey('mail_password', $log->new_values);
    }

    public function test_platform_settings_update_is_audited_without_password(): void
    {
        $this->platformSettingsService->updateSettings($this->root, [
            'public_ip' => '200.10.20.30',
            'mail_host' => 'smtp.platform.com',
            'mail_from_address' => 'no-reply@platform.com',
            'mail_password' => 'PLATFORM_SECRET',
        ]);

        $log = AuditLog::where('action', AuditLog::ACTION_PLATFORM_SETTINGS_UPDATED)
            ->orderByDesc('id')
            ->firstOrFail();

        $blob = json_encode([$log->old_values, $log->new_values, $log->metadata]);
        $this->assertStringNotContainsString('PLATFORM_SECRET', $blob);
        $this->assertTrue($log->new_values['has_mail_password']);
        $this->assertEquals('200.10.20.30', $log->new_values['public_ip']);
    }
}
