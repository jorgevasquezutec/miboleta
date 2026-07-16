<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\AuditSettings;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuditSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Mantenedor de auditoría: activar/desactivar captura por tipo, con guardrail
 * de acciones always-on.
 */
class AuditSettingsTest extends TestCase
{
    use RefreshDatabase;

    private AuditSettingsService $service;
    private AuditService $audit;
    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->audit = new AuditService();
        $this->service = new AuditSettingsService($this->audit);
        $this->root = User::factory()->root()->create(['status' => 'active']);
        Auth::login($this->root);
    }

    public function test_default_captures_everything(): void
    {
        // Sin config previa: toda acción toggleable está activa.
        $this->assertTrue($this->audit->isActionEnabled(AuditLog::ACTION_DOCUMENT_VIEWED));
        $this->assertNotNull($this->audit->logDocumentViewed(1));
    }

    public function test_disabling_a_toggleable_action_suppresses_capture(): void
    {
        $this->service->updateSettings($this->root, [AuditLog::ACTION_DOCUMENT_VIEWED]);

        $this->assertFalse($this->audit->isActionEnabled(AuditLog::ACTION_DOCUMENT_VIEWED));
        // El log no crea fila (retorna null).
        $this->assertNull($this->audit->logDocumentViewed(1));
        $this->assertDatabaseMissing('audit_logs', ['action' => AuditLog::ACTION_DOCUMENT_VIEWED]);
    }

    public function test_reenabling_restores_capture(): void
    {
        $this->service->updateSettings($this->root, [AuditLog::ACTION_DOCUMENT_VIEWED]);
        $this->service->updateSettings($this->root, []);

        $this->assertTrue($this->audit->isActionEnabled(AuditLog::ACTION_DOCUMENT_VIEWED));
        $this->assertNotNull($this->audit->logDocumentViewed(1));
    }

    public function test_always_on_actions_cannot_be_disabled(): void
    {
        // Se intenta desactivar acciones críticas: el service las filtra.
        $this->service->updateSettings($this->root, [
            AuditLog::ACTION_ROLE_ASSIGNED,
            AuditLog::ACTION_PLATFORM_SETTINGS_UPDATED,
            AuditLog::ACTION_DOCUMENT_DELETED,
            AuditLog::ACTION_DOCUMENT_VIEWED, // esta sí es toggleable
        ]);

        $stored = AuditSettings::current()->disabled_actions;
        $this->assertEquals([AuditLog::ACTION_DOCUMENT_VIEWED], $stored);

        // Y siguen capturándose aunque se hayan pedido desactivar (tenant null
        // para no depender de FKs de empresa en este test).
        $this->assertTrue($this->audit->isActionEnabled(AuditLog::ACTION_ROLE_ASSIGNED));
        $this->assertNotNull($this->audit->logRoleAssigned($this->root->id, null, [2], [3]));
    }

    public function test_update_logs_always_on_meta_event(): void
    {
        $this->service->updateSettings($this->root, [AuditLog::ACTION_DOCUMENT_VIEWED]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::ACTION_AUDIT_SETTINGS_UPDATED,
        ]);
        // El meta-evento es always-on: no se puede silenciar.
        $this->assertTrue(AuditLog::isAlwaysOn(AuditLog::ACTION_AUDIT_SETTINGS_UPDATED));
    }

    public function test_catalog_lists_all_actions_with_locked_flag(): void
    {
        $catalog = $this->service->getCatalog($this->root);

        $this->assertCount(count(AuditLog::allActions()), $catalog);

        $byAction = collect($catalog)->keyBy('action');
        $this->assertTrue($byAction[AuditLog::ACTION_ROLE_ASSIGNED]['locked']);
        $this->assertFalse($byAction[AuditLog::ACTION_DOCUMENT_VIEWED]['locked']);
    }

    public function test_non_root_cannot_manage(): void
    {
        $client = User::factory()->client()->create(['status' => 'active']);

        $this->expectException(\App\Exceptions\UnauthorizedAccessException::class);
        $this->service->getCatalog($client);
    }
}
