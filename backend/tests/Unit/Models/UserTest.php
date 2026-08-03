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

    /**
     * scopeMatchingFullName es la fuente única de búsqueda por nombre
     * completo, reutilizada por UserController::index,
     * VacationService::buildAllRequestsQuery y DocumentService::
     * applyOptionalFilters. Antes cada listado comparaba 'name' y
     * 'last_name' por separado contra el término completo, así que
     * "Juan Pérez" no encontraba a un usuario con name=Juan,
     * last_name=Pérez (bug reportado por el cliente en el Histórico de
     * Vacaciones).
     */
    public function test_matching_full_name_finds_user_by_full_name(): void
    {
        User::factory()->create(['name' => 'Juan', 'last_name' => 'Pérez']);
        User::factory()->create(['name' => 'Luis', 'last_name' => 'Ramos']);

        $results = User::query()->matchingFullName('Juan Pérez')->get();

        $this->assertCount(1, $results);
        $this->assertSame('Juan', $results->first()->name);
    }

    /**
     * El orden de las palabras no debe importar: cada término solo necesita
     * aparecer en `name` o `last_name`, no en un campo específico.
     */
    public function test_matching_full_name_ignores_word_order(): void
    {
        User::factory()->create(['name' => 'Juan', 'last_name' => 'Pérez']);

        $results = User::query()->matchingFullName('Pérez Juan')->get();

        $this->assertCount(1, $results);
    }

    /**
     * Sigue funcionando como antes para búsquedas de una sola palabra
     * (por name O por last_name), sin regresión.
     */
    public function test_matching_full_name_still_matches_single_field(): void
    {
        User::factory()->create(['name' => 'Juan', 'last_name' => 'Pérez']);

        $this->assertCount(1, User::query()->matchingFullName('Juan')->get());
        $this->assertCount(1, User::query()->matchingFullName('Pérez')->get());
    }

    /**
     * last_name es nullable: un usuario sin last_name no debe romper el
     * scope ni matchear de más (la palabra buscada debe seguir exigiéndose
     * contra `name` O `last_name`, y NULL simplemente no matchea ese lado).
     * Es justamente el caso que CONCAT(name, ' ', last_name) rompería en
     * MySQL (CONCAT con NULL devuelve NULL): este scope no usa CONCAT.
     */
    public function test_matching_full_name_handles_null_last_name(): void
    {
        User::factory()->create(['name' => 'Solo', 'last_name' => null]);

        $this->assertCount(1, User::query()->matchingFullName('Solo')->get());
        $this->assertCount(0, User::query()->matchingFullName('Solo Apellido')->get());
    }
}
