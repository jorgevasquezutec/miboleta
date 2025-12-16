<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\VacationRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class VacationsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->client()->create(['status' => 'active']);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_user_can_create_vacation_request(): void
    {
        $startDate = now()->addDays(10)->format('Y-m-d');
        $endDate = now()->addDays(15)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->postJson('/api/vacation-requests', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => 5,
                'reason' => 'Vacaciones familiares',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vacation_requests', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_create_vacation_request_with_past_dates(): void
    {
        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->subDays(1)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->postJson('/api/vacation-requests', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => 4,
                'reason' => 'Vacaciones',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_list_their_vacation_requests(): void
    {
        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/vacation-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'start_date',
                        'end_date',
                        'status',
                    ]
                ],
                'meta'
            ]);
    }

    public function test_user_can_view_their_vacation_request(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/vacation-requests/{$vacation->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $vacation->id]);
    }

    public function test_user_cannot_view_other_users_vacation(): void
    {
        $otherUser = User::factory()->client()->create(['status' => 'active']);
        $otherUser->tenants()->attach($this->tenant->id);

        $vacation = VacationRequest::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/vacation-requests/{$vacation->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_cancel_pending_vacation(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/vacation-requests/{$vacation->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_approved_vacation(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/vacation-requests/{$vacation->id}/cancel");

        $response->assertStatus(422);
    }

    public function test_admin_can_approve_vacation(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/vacation-requests/{$vacation->id}/approve");

        $response->assertStatus(200);
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'approved',
            'approved_by' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_vacation(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/vacation-requests/{$vacation->id}/reject", [
                'rejection_reason' => 'Fechas no disponibles',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'rejected',
            'rejection_reason' => 'Fechas no disponibles',
        ]);
    }

    public function test_admin_can_view_all_vacation_requests(): void
    {
        VacationRequest::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/vacation-requests');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'meta'
            ]);
    }

    public function test_user_can_filter_vacations_by_status(): void
    {
        VacationRequest::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/vacation-requests?status=approved');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    public function test_vacation_request_requires_valid_dates(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/vacation-requests', [
                'start_date' => 'invalid-date',
                'end_date' => now()->addDays(5)->format('Y-m-d'),
                'days_requested' => 5,
                'reason' => 'Vacaciones',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        $startDate = now()->addDays(10)->format('Y-m-d');
        $endDate = now()->addDays(5)->format('Y-m-d');

        $response = $this->actingAs($this->user)
            ->postJson('/api/vacation-requests', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => 5,
                'reason' => 'Vacaciones',
            ]);

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_access_vacations(): void
    {
        $response = $this->getJson('/api/vacation-requests');

        $response->assertStatus(401);
    }
}
