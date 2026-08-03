<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VacationRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $employee;
    private User $supervisor;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->supervisor = User::factory()->client()->create(['status' => 'active']);
        $this->supervisor->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->employee = User::factory()->client()->create(['status' => 'active']);
        $this->employee->tenants()->attach($this->tenant->id, [
            'is_primary' => true,
            'supervisor_id' => $this->supervisor->id,
            // Saldo inicial suficiente para que los tests de creación de
            // solicitudes no choquen con la validación de saldo disponible.
            'vacation_balance_initial' => 30,
        ]);

        // withTenantRole asigna el rol DENTRO de la empresa (user_tenant_roles),
        // que es lo que se usa para autorizar; admin() solo escribe el rol
        // global (user_roles), que es un respaldo de display.
        $this->admin = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);
    }

    public function test_employee_can_create_vacation_request(): void
    {
        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->postJson('/api/vacation-requests', [
                'start_date' => now()->addDays(7)->format('Y-m-d'),
                'end_date' => now()->addDays(10)->format('Y-m-d'),
                'days_requested' => 4,
                'reason' => 'Vacaciones familiares',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment([
                'status' => 'pending',
            ]);

        $this->assertDatabaseHas('vacation_requests', [
            'user_id' => $this->employee->id,
            'status' => 'pending',
        ]);
    }

    public function test_create_vacation_validates_dates(): void
    {
        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->postJson('/api/vacation-requests', [
                'start_date' => now()->addDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(5)->format('Y-m-d'), // End before start
                'days_requested' => 4,
            ]);

        $response->assertStatus(422);
    }

    public function test_employee_can_list_own_requests(): void
    {
        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_employee_can_delete_pending_request(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->deleteJson("/api/vacation-requests/{$request->id}");

        $response->assertStatus(200);
    }

    public function test_supervisor_can_approve_request(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->putJson("/api/vacation-requests/{$request->id}/approve");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'approved',
            ]);
    }

    public function test_supervisor_can_reject_request(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->putJson("/api/vacation-requests/{$request->id}/reject", [
                'reason' => 'Necesitamos personal en esas fechas',
            ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'status' => 'rejected',
            ]);
    }

    public function test_admin_can_approve_request(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->putJson("/api/vacation-requests/{$request->id}/approve");

        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'approved']);
    }

    public function test_supervisor_can_mark_as_taken(): void
    {
        $request = VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->putJson("/api/vacation-requests/{$request->id}/mark-taken");

        $response->assertStatus(200);
    }

    public function test_supervisor_can_get_pending_approvals(): void
    {
        VacationRequest::factory()->count(2)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests/pending-approval');

        $response->assertStatus(200);
    }

    public function test_supervisor_can_get_team_requests(): void
    {
        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->supervisor)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests/my-team');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_vacation_requests(): void
    {
        $response = $this->getJson('/api/vacation-requests');

        $response->assertStatus(401);
    }

    // ==========================================
    // Balance (SPEC-VACACIONES v2 — SUPERSEDE el mapeo original de FASE 2 / B1 + E2)
    // ==========================================

    public function test_balance_includes_the_four_client_figures_and_request_counts(): void
    {
        VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests/balance?tenant_id=' . $this->tenant->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'available',
                    'taken',
                    'pending',
                    'truncated',
                    'balance',
                    'months_completed',
                    'current_period_start',
                    'current_period_end',
                    'requests' => ['pending', 'approved'],
                ],
            ])
            ->assertJsonFragment([
                'requests' => ['pending' => 1, 'approved' => 0],
            ]);

        // Empleado sin hire_date en este fixture: pending = initial (30, ver
        // setUp), truncated = 0, balance = pending - taken = 30.
        $response->assertJsonFragment([
            'pending' => 30.0,
            'truncated' => 0.0,
            'balance' => 30.0,
        ]);
    }

    public function test_balance_request_counts_are_not_limited_by_list_pagination(): void
    {
        // Bug E2: los contadores de "Mis Vacaciones" se calculaban con
        // Array.filter sobre los 10 registros de la página actual del
        // listado, no sobre el total real. Con más de 10 solicitudes el
        // conteo debía subestimar, aunque el endpoint de balance no pagine.
        VacationRequest::factory()->count(11)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        VacationRequest::factory()->count(2)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
        ]);
        // Rechazada: no debe contarse en ninguno de los dos conteos.
        VacationRequest::factory()->rejected()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests/balance?tenant_id=' . $this->tenant->id);

        $response->assertStatus(200);
        $this->assertSame(11, $response->json('data.requests.pending'));
        $this->assertSame(2, $response->json('data.requests.approved'));
    }

    public function test_balance_request_counts_are_scoped_to_the_requesting_user(): void
    {
        $otherEmployee = User::factory()->client()->create(['status' => 'active']);
        $otherEmployee->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        VacationRequest::factory()->create([
            'user_id' => $otherEmployee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests/balance?tenant_id=' . $this->tenant->id);

        $response->assertStatus(200);
        $this->assertSame(0, $response->json('data.requests.pending'));
    }

    // ==========================================
    // Histórico de vacaciones (scope=tenant) — conteos de tarjetas (H1)
    // ==========================================

    public function test_history_counts_are_not_limited_by_list_pagination(): void
    {
        // Bug H1: "Aprobadas" y "Tomadas" en VacationHistoryPage se calculaban
        // con Array.filter sobre la página actual del listado (10 por
        // defecto), así que nunca superaban el tamaño de página. Con más de
        // 10 solicitudes filtradas, meta.approved_count / meta.taken_count
        // deben reflejar el TOTAL filtrado, no la página.
        VacationRequest::factory()->count(13)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        VacationRequest::factory()->count(5)->approved()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        VacationRequest::factory()->count(3)->taken()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant&per_page=10');

        $response->assertStatus(200);
        // 13 pending + 5 approved + 3 taken(approved) = 21 solicitudes en total.
        $this->assertSame(21, $response->json('meta.total'));
        // Página actual: solo 10 registros (per_page=10), esto es justo lo
        // que antes limitaba erróneamente los conteos de las tarjetas.
        $this->assertCount(10, $response->json('data'));
        // Aprobadas: las 5 approved() + las 3 taken() (status approved también).
        $this->assertSame(8, $response->json('meta.approved_count'));
        // Tomadas: solo was_taken=true, independientemente de la paginación.
        $this->assertSame(3, $response->json('meta.taken_count'));
    }

    public function test_history_counts_respect_status_filter(): void
    {
        VacationRequest::factory()->count(4)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
        VacationRequest::factory()->count(2)->approved()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant&status=approved');

        $response->assertStatus(200);
        // Con el filtro de estado aplicado, tabla y tarjetas cuadran: el
        // total filtrado y "Aprobadas" son el mismo número.
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.approved_count'));
        $this->assertSame(0, $response->json('meta.taken_count'));
    }

    public function test_history_counts_respect_date_range_filter(): void
    {
        VacationRequest::factory()->approved()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subMonths(2),
        ]);
        VacationRequest::factory()->taken()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant'
                . '&date_from=' . now()->subDays(1)->format('Y-m-d')
                . '&date_to=' . now()->addDays(1)->format('Y-m-d'));

        $response->assertStatus(200);
        // Solo la solicitud creada "hoy" cae dentro del rango; la de hace
        // 2 meses queda fuera tanto de la tabla como de los conteos.
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.approved_count'));
        $this->assertSame(1, $response->json('meta.taken_count'));
    }

    public function test_history_counts_respect_search_filter(): void
    {
        $ana = User::factory()->client()->create(['status' => 'active', 'name' => 'Ana', 'last_name' => 'Torres']);
        $ana->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        $luis = User::factory()->client()->create(['status' => 'active', 'name' => 'Luis', 'last_name' => 'Ramos']);
        $luis->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        VacationRequest::factory()->approved()->create([
            'user_id' => $ana->id,
            'tenant_id' => $this->tenant->id,
        ]);
        VacationRequest::factory()->taken()->create([
            'user_id' => $luis->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant&search=Ana');

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.approved_count'));
        // La de Luis (taken) queda fuera del filtro de búsqueda.
        $this->assertSame(0, $response->json('meta.taken_count'));
    }

    /**
     * Regresión: antes 'name' y 'last_name' se comparaban por separado
     * contra el término completo, así que buscar "Ana Torres" no
     * encontraba a la empleada con name=Ana, last_name=Torres (ninguna
     * columna contiene la cadena completa). Ver User::scopeMatchingFullName,
     * reutilizado por VacationService::buildAllRequestsQuery.
     */
    public function test_history_counts_respect_full_name_search_filter(): void
    {
        $ana = User::factory()->client()->create(['status' => 'active', 'name' => 'Ana', 'last_name' => 'Torres']);
        $ana->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        $luis = User::factory()->client()->create(['status' => 'active', 'name' => 'Luis', 'last_name' => 'Ramos']);
        $luis->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        VacationRequest::factory()->approved()->create([
            'user_id' => $ana->id,
            'tenant_id' => $this->tenant->id,
        ]);
        VacationRequest::factory()->taken()->create([
            'user_id' => $luis->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant&search=' . urlencode('Ana Torres'));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('meta.total'));
        $this->assertSame(1, $response->json('meta.approved_count'));
        $this->assertSame(0, $response->json('meta.taken_count'));
    }

    public function test_history_counts_are_scoped_to_the_active_tenant_for_non_root(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);

        VacationRequest::factory()->count(2)->approved()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        // Solicitudes de OTRA empresa: el admin de $this->tenant no debe
        // verlas ni en la tabla ni en los conteos.
        VacationRequest::factory()->count(4)->taken()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant');

        $response->assertStatus(200);
        $this->assertSame(2, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.approved_count'));
        $this->assertSame(0, $response->json('meta.taken_count'));
    }

    public function test_history_counts_not_present_for_non_admin_scope(): void
    {
        // scope=tenant solo aplica para root/admin (VacationRequestController::index).
        // Un employee siempre cae en getRequestsForUser(), que no calcula
        // estos conteos: no deben aparecer en la respuesta.
        $response = $this->actingAs($this->employee)
            ->withHeader('X-Tenant-Ids', $this->tenant->id)
            ->getJson('/api/vacation-requests?scope=tenant');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('approved_count', $response->json('meta'));
        $this->assertArrayNotHasKey('taken_count', $response->json('meta'));
    }
}
