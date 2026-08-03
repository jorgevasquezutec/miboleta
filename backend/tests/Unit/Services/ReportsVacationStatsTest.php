<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\ReportsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests para el bug de ReportsService::getVacationStats() (E3): filtraba
 * por status ['pending_confirmation', 'confirmed', 'taken'], que NUNCA
 * existieron en el enum real de VacationRequest::status
 * (pending, approved, rejected, cancelled) -> pending/approved/total_days_used
 * daban 0 siempre, aunque hubiera solicitudes reales ese año.
 *
 * Las claves del JSON de respuesta se mantienen intactas (las consume
 * DashboardPage.tsx vía reportsStore.vacationStats): solo se corrigen las
 * queries.
 */
class ReportsVacationStatsTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $reportsService;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->reportsService = app(ReportsService::class);
        $this->tenant = Tenant::factory()->create(['status' => 'active']);
    }

    public function test_vacation_stats_counts_requests_by_real_status(): void
    {
        $currentYear = now()->year;

        VacationRequest::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        VacationRequest::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        VacationRequest::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
        ]);

        $stats = $this->reportsService->getVacationStats($this->tenant->id);

        // Con el bug viejo (['pending', 'pending_confirmation']) el resultado
        // era el mismo aquí porque 'pending' seguía en la lista, pero
        // 'approved' (sin 'confirmed') ya distinguía el fix real.
        $this->assertSame(6, $stats['total']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(3, $stats['approved']);
        $this->assertSame(1, $stats['rejected']);
        $this->assertSame($currentYear, $stats['current_year']);
    }

    public function test_vacation_stats_total_days_used_only_counts_approved_and_taken(): void
    {
        // Gozadas de verdad: aprobada Y confirmada como tomada (was_taken=true).
        VacationRequest::factory()->taken()->create([
            'tenant_id' => $this->tenant->id,
            'days_requested' => 5,
            'created_at' => now(),
        ]);

        // Aprobada pero AÚN no tomada: con el bug viejo (status IN
        // ['approved','confirmed','taken']) esto ya contaba como "usado"
        // aunque el empleado no se hubiera ido de vacaciones todavía.
        VacationRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'was_taken' => null,
            'days_requested' => 8,
            'created_at' => now(),
        ]);

        // Pendiente: nunca debe sumar días usados.
        VacationRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'days_requested' => 3,
            'created_at' => now(),
        ]);

        $stats = $this->reportsService->getVacationStats($this->tenant->id);

        $this->assertEquals(5, $stats['total_days_used']);
    }

    public function test_vacation_stats_are_never_zero_when_current_year_has_real_data(): void
    {
        // Regresión directa del bug: antes esto SIEMPRE daba pending=0 y
        // approved=0 porque los status del filtro no existían en la tabla.
        VacationRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        VacationRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $stats = $this->reportsService->getVacationStats($this->tenant->id);

        $this->assertGreaterThan(0, $stats['pending']);
        $this->assertGreaterThan(0, $stats['approved']);
    }
}
