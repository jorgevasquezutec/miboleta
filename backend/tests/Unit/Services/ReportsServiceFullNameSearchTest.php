<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserTenantRole;
use App\Models\VacationRequest;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresión del mismo bug que User::scopeMatchingFullName corrige en los
 * listados (ver su docblock), pero en los exports de ReportsService: antes
 * se comparaba el término completo contra `name` y `last_name` por
 * separado, así que "Ana Torres" nunca encontraba a name=Ana,
 * last_name=Torres (ninguna columna contiene la cadena completa).
 *
 * Cubre los tres métodos que tenían el bug:
 * - getVacationReportData (whereHas('user', ...))
 * - getUsersReportData (query directa sobre User)
 * - getAuditLogs (whereHas('user', ...), usado también por el export de
 *   auditoría vía ReportsController::exportAudit)
 *
 * getDocumentReportData no tiene filtro de búsqueda por nombre, y
 * getTenantReportData / TenantService::getTenants buscan campos de EMPRESA
 * (name/ruc/business_name), no de persona — no aplica este bug ahí.
 */
class ReportsServiceFullNameSearchTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;
    private Tenant $tenant;
    private User $ana;
    private User $luis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->service = app(ReportsService::class);
        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $clientRole = Role::where('name', 'client')->firstOrFail();

        // Email FIJADO, no el de la factory: la búsqueda de estos exports hace
        // `orWhere('email', 'like', "%term%")`, así que un email de faker que
        // contenga por casualidad la subcadena buscada mete una fila de más y
        // rompe los assertCount(1). Con 'Ana' pasa a menudo — susana,
        // mariana, ana.lopez… — y el test fallaba de forma intermitente solo
        // en CI. Los valores de abajo no contienen ninguno de los términos que
        // busca este archivo (Ana, Torres, Ramos, Luis).
        $this->ana = User::factory()->create([
            'status' => 'active',
            'name' => 'Ana',
            'last_name' => 'Torres',
            'email' => 'empleada.primera@example.test',
            'document_text' => '10000001',
        ]);
        $this->ana->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        // Empleado (rol 'client') EN esta empresa: getUsersReportData()
        // filtra por User::ORG_EMPLOYEE_ROLES, un usuario sin tenantRoles no
        // saldría en el export.
        UserTenantRole::create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $clientRole->id,
        ]);

        $this->luis = User::factory()->create([
            'status' => 'active',
            'name' => 'Luis',
            'last_name' => 'Ramos',
            'email' => 'empleado.segundo@example.test',
            'document_text' => '10000002',
        ]);
        $this->luis->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        UserTenantRole::create([
            'user_id' => $this->luis->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $clientRole->id,
        ]);
    }

    // ===================== getVacationReportData =====================

    public function test_vacation_export_finds_full_name(): void
    {
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
        ]);
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->luis->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $rows = $this->service->getVacationReportData(['search' => 'Ana Torres']);

        // 'empleado' solo expone $user->name (no el nombre completo) —
        // comportamiento preexistente de la columna del export, ajeno a
        // este bug. Se identifica la fila por email.
        $this->assertCount(1, $rows);
        $this->assertSame($this->ana->email, $rows->first()['email']);
    }

    public function test_vacation_export_search_still_matches_by_first_name_or_last_name_alone(): void
    {
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
        ]);
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->luis->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $byFirstName = $this->service->getVacationReportData(['search' => 'Ana']);
        $byLastName = $this->service->getVacationReportData(['search' => 'Ramos']);

        $this->assertCount(1, $byFirstName);
        $this->assertSame($this->ana->email, $byFirstName->first()['email']);
        $this->assertCount(1, $byLastName);
        $this->assertSame($this->luis->email, $byLastName->first()['email']);
    }

    public function test_vacation_export_search_still_matches_by_email(): void
    {
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $rows = $this->service->getVacationReportData(['search' => $this->ana->email]);

        $this->assertCount(1, $rows);
        $this->assertSame($this->ana->email, $rows->first()['email']);
    }

    // ======================= getUsersReportData =======================

    public function test_users_export_finds_full_name(): void
    {
        $rows = $this->service->getUsersReportData(['search' => 'Ana Torres']);

        $this->assertCount(1, $rows);
        $this->assertSame($this->ana->id, $rows->first()['ID']);
    }

    public function test_users_export_search_still_matches_by_first_name_or_last_name_alone(): void
    {
        $byFirstName = $this->service->getUsersReportData(['search' => 'Ana']);
        $byLastName = $this->service->getUsersReportData(['search' => 'Ramos']);

        $this->assertCount(1, $byFirstName);
        $this->assertSame($this->ana->id, $byFirstName->first()['ID']);
        $this->assertCount(1, $byLastName);
        $this->assertSame($this->luis->id, $byLastName->first()['ID']);
    }

    public function test_users_export_search_still_matches_by_email_and_document_text(): void
    {
        $byEmail = $this->service->getUsersReportData(['search' => $this->ana->email]);
        $byDocumentText = $this->service->getUsersReportData(['search' => $this->luis->document_text]);

        $this->assertCount(1, $byEmail);
        $this->assertSame($this->ana->id, $byEmail->first()['ID']);
        $this->assertCount(1, $byDocumentText);
        $this->assertSame($this->luis->id, $byDocumentText->first()['ID']);
    }

    // ========================== getAuditLogs ===========================

    public function test_audit_logs_search_finds_full_name(): void
    {
        AuditLog::create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
            'action' => AuditLog::ACTION_PROFILE_UPDATED,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $this->luis->id,
            'tenant_id' => $this->tenant->id,
            'action' => AuditLog::ACTION_PROFILE_UPDATED,
            'created_at' => now(),
        ]);

        $logs = $this->service->getAuditLogs(['search' => 'Ana Torres']);

        $this->assertSame(1, $logs->total());
        $this->assertSame($this->ana->id, $logs->items()[0]->user_id);
    }

    public function test_audit_logs_search_still_matches_by_first_name_or_action(): void
    {
        AuditLog::create([
            'user_id' => $this->ana->id,
            'tenant_id' => $this->tenant->id,
            'action' => AuditLog::ACTION_PROFILE_UPDATED,
            'created_at' => now(),
        ]);
        AuditLog::create([
            'user_id' => $this->luis->id,
            'tenant_id' => $this->tenant->id,
            'action' => AuditLog::ACTION_USER_LOGIN,
            'created_at' => now(),
        ]);

        $byFirstName = $this->service->getAuditLogs(['search' => 'Ana']);
        $byAction = $this->service->getAuditLogs(['search' => 'user.login']);

        $this->assertSame(1, $byFirstName->total());
        $this->assertSame($this->ana->id, $byFirstName->items()[0]->user_id);
        $this->assertSame(1, $byAction->total());
        $this->assertSame($this->luis->id, $byAction->items()[0]->user_id);
    }
}
