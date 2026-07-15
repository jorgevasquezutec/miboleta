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
}
