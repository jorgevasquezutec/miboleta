<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Document;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_has_correct_default_status(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertEquals('active', $tenant->status);
    }

    public function test_tenant_can_be_inactive(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->assertEquals('inactive', $tenant->status);
    }

    public function test_tenant_can_have_users(): void
    {
        $tenant = Tenant::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            $tenant->users()->attach($user->id);
        }

        $this->assertCount(3, $tenant->users);
    }

    public function test_tenant_can_have_documents(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        Document::factory()->count(5)->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(5, $tenant->documents);
    }

    public function test_tenant_has_ruc(): void
    {
        $tenant = Tenant::factory()->create([
            'ruc' => '12345678901',
        ]);

        $this->assertEquals('12345678901', $tenant->ruc);
    }

    /**
     * Fuente única de los contadores de empleados (RP1-C), compartida por
     * TenantService::transformTenantForList y TenantResource (ver
     * TenantControllerTest::test_employee_counts_match_between_list_and_resource_views
     * para la prueba de que ambas vías coinciden).
     */
    public function test_employee_counts_formula(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 3]);
        $users = User::factory()->count(5)->create();
        foreach ($users as $user) {
            $tenant->users()->attach($user->id);
        }

        $counts = $tenant->employeeCounts();

        $this->assertSame(5, $counts['current_employee_count']);
        $this->assertSame(3, $counts['initial_employee_count']);
        // "La carga inicial es la primera, luego de eso ya todo es nuevo":
        // subsequent = current - initial = 5 - 3 = 2.
        $this->assertSame(2, $counts['subsequent_employee_count']);
    }

    /**
     * subsequent_employee_count nunca debe ser negativo (p.ej. si se dieron
     * de baja usuarios después de la carga inicial y current < initial).
     */
    public function test_employee_counts_subsequent_never_negative(): void
    {
        $tenant = Tenant::factory()->create(['initial_employee_count' => 10]);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id);

        $counts = $tenant->employeeCounts();

        $this->assertSame(1, $counts['current_employee_count']);
        $this->assertSame(10, $counts['initial_employee_count']);
        $this->assertSame(0, $counts['subsequent_employee_count']);
    }
}
