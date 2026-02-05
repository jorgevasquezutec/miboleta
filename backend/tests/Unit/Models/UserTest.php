<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_full_name_attribute(): void
    {
        $user = User::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
        ]);

        $this->assertEquals('Juan Pérez', $user->fullName);
    }

    public function test_user_has_correct_default_status(): void
    {
        $user = User::factory()->create();

        $this->assertEquals('active', $user->status);
    }

    public function test_user_can_be_inactive(): void
    {
        $user = User::factory()->inactive()->create();

        $this->assertEquals('inactive', $user->status);
        $this->assertFalse($user->isActive());
    }

    public function test_user_is_active_method_works(): void
    {
        $activeUser = User::factory()->create(['status' => 'active']);
        $inactiveUser = User::factory()->create(['status' => 'inactive']);

        $this->assertTrue($activeUser->isActive());
        $this->assertFalse($inactiveUser->isActive());
    }

    public function test_user_can_belong_to_multiple_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user->tenants()->attach([
            $tenant1->id => ['is_primary' => true],
            $tenant2->id => ['is_primary' => false],
        ]);

        $this->assertCount(2, $user->tenants);
    }

    public function test_user_has_primary_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $primaryTenant = $user->primaryTenant();
        $this->assertNotNull($primaryTenant);
        $this->assertEquals($tenant->id, $primaryTenant->id);
    }

    public function test_user_belongs_to_tenant(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user->tenants()->attach($tenant1->id);

        $this->assertTrue($user->belongsToTenant($tenant1->id));
        $this->assertFalse($user->belongsToTenant($tenant2->id));
    }

    public function test_user_can_have_supervisor(): void
    {
        $tenant = Tenant::factory()->create();
        $supervisor = User::factory()->create();
        $employee = User::factory()->create();

        // Attach supervisor to employee through the pivot table
        $employee->tenants()->attach($tenant->id, [
            'is_primary' => true,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->assertEquals($supervisor->id, $employee->getSupervisorForTenant($tenant->id)->id);
    }

    public function test_supervisor_can_have_subordinates(): void
    {
        $tenant = Tenant::factory()->create();
        $supervisor = User::factory()->create();

        // Attach supervisor to tenant first
        $supervisor->tenants()->attach($tenant->id, ['is_primary' => true]);

        // Create 3 employees with this supervisor
        $employees = User::factory()->count(3)->create();
        foreach ($employees as $employee) {
            $employee->tenants()->attach($tenant->id, [
                'is_primary' => true,
                'supervisor_id' => $supervisor->id,
            ]);
        }

        $this->assertCount(3, $supervisor->subordinates);
    }

    public function test_user_must_change_password_flag(): void
    {
        $user = User::factory()->mustChangePassword()->create();

        $this->assertTrue($user->must_change_password);
    }
}
