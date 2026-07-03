<?php

namespace Tests\Unit\Services;

use App\Exceptions\UnauthorizedAccessException;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\AuditService;
use App\Services\NotificationService;
use App\Services\VacationBalanceService;
use App\Services\VacationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VacationServiceTest extends TestCase
{
    use RefreshDatabase;

    private VacationService $vacationService;
    private User $employee;
    private User $supervisor;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $notificationService = $this->createMock(NotificationService::class);
        $auditService = $this->createMock(AuditService::class);

        $this->vacationService = new VacationService($notificationService, $auditService, new VacationBalanceService());

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->supervisor = User::factory()->client()->create(['status' => 'active']);
        $this->supervisor->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->employee = User::factory()->client()->create(['status' => 'active']);
        $this->employee->tenants()->attach($this->tenant->id, [
            'is_primary' => true,
            'supervisor_id' => $this->supervisor->id,
            // Saldo inicial suficiente para que los tests de creación de
            // solicitudes (p. ej. 4-5 días) no choquen con la validación de
            // saldo disponible (VacationService::validateSufficientBalance).
            'vacation_balance_initial' => 30,
        ]);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_create_request_succeeds_with_valid_data(): void
    {
        $data = [
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'days_requested' => 4,
            'reason' => 'Vacaciones familiares',
        ];

        $request = $this->vacationService->createRequest($data, $this->employee, $this->tenant->id);

        $this->assertInstanceOf(VacationRequest::class, $request);
        $this->assertEquals($this->employee->id, $request->user_id);
        $this->assertEquals($this->tenant->id, $request->tenant_id);
        $this->assertEquals('pending', $request->status);
    }

    public function test_create_request_fails_without_supervisor(): void
    {
        $employeeWithoutSupervisor = User::factory()->client()->create(['status' => 'active']);
        $employeeWithoutSupervisor->tenants()->attach($this->tenant->id, [
            'is_primary' => true,
            'supervisor_id' => null,
        ]);

        $data = [
            'start_date' => now()->addDays(7)->format('Y-m-d'),
            'end_date' => now()->addDays(10)->format('Y-m-d'),
            'days_requested' => 4,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No tienes un supervisor asignado');

        $this->vacationService->createRequest($data, $employeeWithoutSupervisor, $this->tenant->id);
    }

    public function test_create_request_validates_no_overlap_with_pending(): void
    {
        VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(10),
            'status' => 'pending',
        ]);

        $data = [
            'start_date' => now()->addDays(8)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'days_requested' => 5,
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Ya tienes vacaciones solicitadas');

        $this->vacationService->createRequest($data, $this->employee, $this->tenant->id);
    }

    public function test_create_request_validates_no_overlap_with_approved(): void
    {
        VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(10),
            'approved_by' => $this->supervisor->id,
        ]);

        $data = [
            'start_date' => now()->addDays(8)->format('Y-m-d'),
            'end_date' => now()->addDays(12)->format('Y-m-d'),
            'days_requested' => 5,
        ];

        $this->expectException(\InvalidArgumentException::class);

        $this->vacationService->createRequest($data, $this->employee, $this->tenant->id);
    }

    public function test_approve_request_by_supervisor(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $approved = $this->vacationService->approveRequest($request, $this->supervisor);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($this->supervisor->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
    }

    public function test_approve_request_by_admin(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $approved = $this->vacationService->approveRequest($request, $this->admin);

        $this->assertEquals('approved', $approved->status);
        $this->assertEquals($this->admin->id, $approved->approved_by);
    }

    public function test_approve_fails_for_non_supervisor(): void
    {
        $otherUser = User::factory()->client()->create(['status' => 'active']);
        $otherUser->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->expectException(UnauthorizedAccessException::class);

        $this->vacationService->approveRequest($request, $otherUser);
    }

    public function test_cannot_approve_non_pending_request(): void
    {
        $request = VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ya no está pendiente');

        $this->vacationService->approveRequest($request, $this->supervisor);
    }

    public function test_reject_request_by_supervisor(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $rejected = $this->vacationService->rejectRequest($request, $this->supervisor, 'Necesitamos personal');

        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals($this->supervisor->id, $rejected->rejected_by);
        $this->assertEquals('Necesitamos personal', $rejected->rejection_reason);
        $this->assertNotNull($rejected->rejected_at);
    }

    public function test_reject_fails_for_non_supervisor(): void
    {
        $otherUser = User::factory()->client()->create(['status' => 'active']);
        $otherUser->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->expectException(UnauthorizedAccessException::class);

        $this->vacationService->rejectRequest($request, $otherUser, 'Razón');
    }

    public function test_cancel_request_by_owner(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $cancelled = $this->vacationService->cancelRequest($request, $this->employee);

        $this->assertEquals('cancelled', $cancelled->status);
    }

    public function test_cancel_fails_for_non_owner(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->expectException(UnauthorizedAccessException::class);
        $this->expectExceptionMessage('No puedes cancelar esta solicitud');

        $this->vacationService->cancelRequest($request, $this->supervisor);
    }

    public function test_cannot_cancel_approved_request(): void
    {
        $request = VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo puedes cancelar solicitudes pendientes');

        $this->vacationService->cancelRequest($request, $this->employee);
    }

    public function test_mark_as_taken_by_supervisor(): void
    {
        $request = VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $confirmed = $this->vacationService->markAsTaken($request, $this->supervisor);

        $this->assertTrue($confirmed->was_taken);
        $this->assertEquals($this->supervisor->id, $confirmed->confirmed_by);
        $this->assertNotNull($confirmed->confirmed_at);
    }

    public function test_mark_as_not_taken_by_supervisor(): void
    {
        $request = VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $confirmed = $this->vacationService->markAsNotTaken($request, $this->supervisor);

        $this->assertFalse($confirmed->was_taken);
        $this->assertEquals($this->supervisor->id, $confirmed->confirmed_by);
    }

    public function test_cannot_mark_pending_request_as_taken(): void
    {
        $request = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Solo puedes confirmar vacaciones aprobadas');

        $this->vacationService->markAsTaken($request, $this->supervisor);
    }

    public function test_get_requests_for_user(): void
    {
        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
        ]);

        VacationRequest::factory()->create([
            'user_id' => $this->supervisor->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $requests = $this->vacationService->getRequestsForUser($this->employee);

        $this->assertEquals(3, $requests->total());
    }

    public function test_get_pending_approvals_for_supervisor(): void
    {
        VacationRequest::factory()->count(2)->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        VacationRequest::factory()->approved()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $this->supervisor->id,
        ]);

        $pending = $this->vacationService->getPendingApprovals($this->supervisor);

        $this->assertEquals(2, $pending->total());
    }

    public function test_validate_no_overlap_with_exclude_id(): void
    {
        $existingRequest = VacationRequest::factory()->create([
            'user_id' => $this->employee->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->addDays(7),
            'end_date' => now()->addDays(10),
            'status' => 'pending',
        ]);

        // Should not throw when excluding its own ID (for updates)
        $this->vacationService->validateNoOverlap(
            $this->employee->id,
            now()->addDays(7)->format('Y-m-d'),
            now()->addDays(10)->format('Y-m-d'),
            $existingRequest->id
        );

        $this->assertTrue(true); // If we get here, no exception was thrown
    }
}
