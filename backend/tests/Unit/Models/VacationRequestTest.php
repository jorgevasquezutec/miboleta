<?php

namespace Tests\Unit\Models;

use App\Models\VacationRequest;
use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VacationRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_vacation_request_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $vacation = VacationRequest::factory()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertEquals($user->id, $vacation->user->id);
    }

    public function test_vacation_request_has_correct_default_status(): void
    {
        $vacation = VacationRequest::factory()->create();

        $this->assertEquals('pending', $vacation->status);
        $this->assertTrue($vacation->isPending());
    }

    public function test_vacation_can_be_approved(): void
    {
        $approver = User::factory()->create();
        $vacation = VacationRequest::factory()->create();

        $result = $vacation->approve($approver);

        $this->assertTrue($result);
        $this->assertEquals('approved', $vacation->status);
        $this->assertEquals($approver->id, $vacation->approved_by);
        $this->assertNotNull($vacation->approved_at);
    }

    public function test_vacation_can_be_rejected(): void
    {
        $rejector = User::factory()->create();
        $vacation = VacationRequest::factory()->create();

        $result = $vacation->reject($rejector, 'Fechas no disponibles');

        $this->assertTrue($result);
        $this->assertEquals('rejected', $vacation->status);
        $this->assertEquals($rejector->id, $vacation->rejected_by);
        $this->assertEquals('Fechas no disponibles', $vacation->rejection_reason);
    }

    public function test_vacation_can_be_cancelled(): void
    {
        $vacation = VacationRequest::factory()->create();

        $result = $vacation->cancel();

        $this->assertTrue($result);
        $this->assertEquals('cancelled', $vacation->status);
    }

    public function test_approved_vacation_cannot_be_approved_again(): void
    {
        $approver = User::factory()->create();
        $vacation = VacationRequest::factory()->approved()->create([
            'approved_by' => $approver->id,
        ]);

        $result = $vacation->approve($approver);

        $this->assertFalse($result);
    }

    public function test_vacation_can_be_marked_as_taken(): void
    {
        $approver = User::factory()->create();
        $confirmer = User::factory()->create();

        $vacation = VacationRequest::factory()->approved()->create([
            'approved_by' => $approver->id,
        ]);

        $result = $vacation->markAsTaken($confirmer);

        $this->assertTrue($result);
        $this->assertTrue($vacation->was_taken);
        $this->assertEquals($confirmer->id, $vacation->confirmed_by);
    }

    public function test_vacation_can_be_marked_as_not_taken(): void
    {
        $approver = User::factory()->create();
        $confirmer = User::factory()->create();

        $vacation = VacationRequest::factory()->approved()->create([
            'approved_by' => $approver->id,
        ]);

        $result = $vacation->markAsNotTaken($confirmer);

        $this->assertTrue($result);
        $this->assertFalse($vacation->was_taken);
    }

    public function test_vacation_status_label_attribute(): void
    {
        $pending = VacationRequest::factory()->create(['status' => 'pending']);
        $approved = VacationRequest::factory()->approved()->create();
        $rejected = VacationRequest::factory()->rejected()->create();
        $cancelled = VacationRequest::factory()->cancelled()->create();

        $this->assertEquals('Pendiente', $pending->statusLabel);
        $this->assertEquals('Aprobada', $approved->statusLabel);
        $this->assertEquals('Rechazada', $rejected->statusLabel);
        $this->assertEquals('Cancelada', $cancelled->statusLabel);
    }

    public function test_vacation_duration_text(): void
    {
        $singleDay = VacationRequest::factory()->create(['days_requested' => 1]);
        $halfDay = VacationRequest::factory()->create(['days_requested' => 0.5]);
        $multipleDays = VacationRequest::factory()->create(['days_requested' => 5]);

        $this->assertEquals('1 día', $singleDay->durationText);
        $this->assertEquals('Medio día', $halfDay->durationText);
        $this->assertEquals('5 días', $multipleDays->durationText);
    }

    public function test_vacation_date_range(): void
    {
        $vacation = VacationRequest::factory()->create([
            'start_date' => '2025-01-15',
            'end_date' => '2025-01-20',
        ]);

        $this->assertStringContainsString('15/01/2025', $vacation->dateRange);
        $this->assertStringContainsString('20/01/2025', $vacation->dateRange);
    }

    public function test_vacation_scope_for_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenant = Tenant::factory()->create();

        VacationRequest::factory()->count(3)->create([
            'user_id' => $user1->id,
            'tenant_id' => $tenant->id,
        ]);

        VacationRequest::factory()->count(2)->create([
            'user_id' => $user2->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertCount(3, VacationRequest::forUser($user1->id)->get());
        $this->assertCount(2, VacationRequest::forUser($user2->id)->get());
    }

    public function test_vacation_scope_pending(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        VacationRequest::factory()->count(3)->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'status' => 'pending',
        ]);

        VacationRequest::factory()->approved()->count(2)->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
        ]);

        $this->assertCount(3, VacationRequest::pending()->get());
    }
}
