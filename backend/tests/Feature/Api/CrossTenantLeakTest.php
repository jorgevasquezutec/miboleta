<?php

namespace Tests\Feature\Api;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EL TEST ESTRELLA: fuga de privilegios ENTRE EMPRESAS, módulo por módulo.
 *
 * Escenario, siempre el mismo: un usuario con un rol PODEROSO en la empresa A y
 * uno LIMITADO en la B, operando sobre recursos de B.
 *
 * Antes de la Matriz de Accesos esto pasaba en producción: la autorización
 * resolvía el rol con getCurrentRole() sin empresa, que cae al respaldo global
 * `user_roles` — la UNIÓN de los roles del usuario en TODAS sus empresas, con
 * ->first() sin ORDER BY (no determinístico). El rol de A resolvía 'admin' y
 * bastaba para pasar los checks operando sobre B.
 *
 * Cada test fija la regla crítica del diseño: para acciones sobre un recurso
 * concreto el rol se resuelve en la empresa DEL RECURSO ($document->tenant_id),
 * no en la empresa "activa" de la sesión ni en el respaldo global.
 *
 * Los usuarios se crean con ->admin() ADEMÁS de los roles por empresa: ese state
 * escribe el rol global de respaldo, igual que hace
 * UserService::syncGlobalRoleFallback en producción. Sin él estos tests no
 * probarían nada — pasarían por ausencia del respaldo, no porque la
 * autorización lo ignore.
 */
class CrossTenantLeakTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenantA = Tenant::factory()->create(['status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['status' => 'active']);
    }

    /** Admin en A, simple client en B. El respaldo global dice 'admin'. */
    private function adminInAClientInB(): User
    {
        return User::factory()
            ->admin()
            ->withTenantRole($this->tenantA, 'admin', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);
    }

    // ── DOCUMENTOS ──────────────────────────────────────────────────────────

    public function test_admin_in_other_tenant_cannot_read_foreign_document_of_tenant_where_he_is_client(): void
    {
        $intruder = $this->adminInAClientInB();

        // Documento de OTRA persona, en la empresa donde el intruso es client.
        $owner = User::factory()->withTenantRole($this->tenantB, 'client', true)->create();
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->getJson("/api/documents/{$document->id}");

        // 'documents.view_org' en B exige admin/admin_tenant; allí es client.
        $response->assertStatus(403);
    }

    public function test_admin_can_read_foreign_document_of_tenant_where_he_is_admin(): void
    {
        // Contraparte: evita que el test de arriba pase por un deny-all.
        $admin = $this->adminInAClientInB();

        $owner = User::factory()->withTenantRole($this->tenantA, 'client', true)->create();
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(200);
    }

    public function test_document_authorization_uses_resource_tenant_not_active_header(): void
    {
        // La regla crítica: el rol se resuelve en la empresa DEL DOCUMENTO, no
        // en la "activa" de la sesión.
        //
        // El header lleva A Y B a la vez (el filtro admite varias empresas), a
        // propósito: con solo A el documento de B quedaría fuera del scope de
        // TenantFilter y la respuesta sería 404 sin llegar a evaluar la
        // autorización — el test pasaría sin probar nada. Enviando ambas, el
        // documento es visible y quien tiene que negar es la ability, resuelta
        // en B (donde el intruso es client), no en A (donde es admin).
        $intruder = $this->adminInAClientInB();

        $owner = User::factory()->withTenantRole($this->tenantB, 'client', true)->create();
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', "{$this->tenantA->id},{$this->tenantB->id}")
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
    }

    public function test_admin_in_other_tenant_cannot_delete_foreign_document(): void
    {
        $intruder = $this->adminInAClientInB();

        $owner = User::factory()->withTenantRole($this->tenantB, 'client', true)->create();
        $document = Document::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'deleted_at' => null]);
    }

    // ── VACACIONES ──────────────────────────────────────────────────────────

    public function test_approver_in_other_tenant_cannot_approve_request_of_tenant_where_he_is_client(): void
    {
        // Aprobador en A, client en B: no puede aprobar vacaciones de B.
        $intruder = User::factory()
            ->admin()
            ->withTenantRole($this->tenantA, 'aprobador', true)
            ->withTenantRole($this->tenantB, 'client')
            ->create(['status' => 'active']);

        $employee = User::factory()->withTenantRole($this->tenantB, 'client', true)->create();
        $request = VacationRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->putJson("/api/vacation-requests/{$request->id}/approve");

        $response->assertStatus(403);
        $this->assertDatabaseHas('vacation_requests', ['id' => $request->id, 'status' => 'pending']);
    }

    public function test_approver_cannot_approve_request_of_tenant_where_he_has_no_role_at_all(): void
    {
        // Sin rol en la empresa del recurso => fail-closed (roleForTenant null).
        $intruder = User::factory()
            ->admin()
            ->withTenantRole($this->tenantA, 'aprobador', true)
            ->create(['status' => 'active']);

        $employee = User::factory()->withTenantRole($this->tenantB, 'client', true)->create();
        $request = VacationRequest::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $employee->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($intruder)
            ->putJson("/api/vacation-requests/{$request->id}/approve");

        // 404, no 403: el usuario no pertenece a B, así que el scope de
        // TenantFilter ni siquiera le deja ver la solicitud y findOrFail corta
        // antes que la ability. Es una denegación legítima —y preferible, porque
        // no revela que el recurso existe—. Lo que importa es lo de abajo: la
        // solicitud NO se aprobó.
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('vacation_requests', ['id' => $request->id, 'status' => 'pending']);
    }

    // ── CARGA MASIVA (FormRequest sobre la empresa ACTIVA) ──────────────────

    public function test_zip_batch_upload_denied_in_tenant_where_user_is_client(): void
    {
        // Aquí no hay recurso previo: el rol se resuelve en la empresa ACTIVA
        // del header (X-Tenant-Ids), ya validada por TenantFilter.
        $intruder = $this->adminInAClientInB();

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->postJson('/api/document-batches/upload', []);

        // 'documents.bulk_upload_zip' exige admin/admin_tenant; en B es client.
        $response->assertStatus(403);
    }

    public function test_zip_batch_upload_reaches_validation_in_tenant_where_user_is_admin(): void
    {
        // Contraparte: en A sí es admin, así que authorize() pasa y la petición
        // llega a la validación (422 por payload vacío), no a un 403.
        $admin = $this->adminInAClientInB();

        $response = $this->actingAs($admin)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantA->id)
            ->postJson('/api/document-batches/upload', []);

        $response->assertStatus(422);
    }

    public function test_user_bulk_upload_denied_in_tenant_where_user_is_client(): void
    {
        $intruder = $this->adminInAClientInB();

        $response = $this->actingAs($intruder)
            ->withHeader('X-Tenant-Ids', (string) $this->tenantB->id)
            ->postJson('/api/user-batches', []);

        $response->assertStatus(403);
    }
}
