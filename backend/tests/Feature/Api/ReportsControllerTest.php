<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Reportes: resolución de la empresa activa y gate de acceso.
 *
 * Regresión principal: getTenantId() referenciaba una variable $role
 * inexistente (huérfana del refactor multitenant), y Laravel convertía el
 * warning en ErrorException -> 500 para todo usuario no-root.
 *
 * Además cubre que la empresa activa NUNCA sea una empresa ajena ni null para
 * un no-root (null = "todas las empresas" en ReportsService).
 */
class ReportsControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Tenant $tenantC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        // Shim de compatibilidad SQLite (solo para tests): ReportsService
        // agrupa documentos por mes con DB::raw('YEAR(created_at)'/'MONTH(...)'),
        // funciones nativas de MySQL (la BD real de producción, ver
        // docker-compose.yml). El test suite corre sobre sqlite in-memory
        // (phpunit.xml), que no las conoce, así que /api/reports/dashboard y
        // /api/reports/documents daban 500 aquí aun con el fix del sprint ya
        // aplicado. No es el bug bajo prueba (la variable $role huérfana): es
        // una limitación de portabilidad del driver de test, preexistente y
        // ajena al fix de ReportsController. Se registra el equivalente vía
        // PDO::sqliteCreateFunction para poder ejercer el endpoint real sin
        // tocar app/ ni ajustar ninguna aserción.
        DB::connection()->getPdo()->sqliteCreateFunction(
            'YEAR',
            fn (?string $date) => $date ? (int) date('Y', strtotime($date)) : null
        );
        DB::connection()->getPdo()->sqliteCreateFunction(
            'MONTH',
            fn (?string $date) => $date ? (int) date('n', strtotime($date)) : null
        );

        $this->tenantA = Tenant::factory()->create(['name' => 'Empresa A', 'status' => 'active']);
        $this->tenantB = Tenant::factory()->create(['name' => 'Empresa B', 'status' => 'active']);
        $this->tenantC = Tenant::factory()->create(['name' => 'Empresa C', 'status' => 'active']);
    }

    // ==========================================
    // Empresa activa (resolveActiveTenantId / getTenantId)
    // ==========================================

    public function test_dashboard_returns_200_for_admin_tenant_user(): void
    {
        // Regresión del 500: antes reventaba en $role (variable indefinida).
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/reports/dashboard');

        $response->assertStatus(200);
        $this->assertSame($this->tenantA->id, $response->json('tenant_id'));
    }

    public function test_dashboard_respects_company_switcher_header(): void
    {
        // El switcher de empresa manda X-Tenant-Ids; TenantFilter ya lo validó.
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->withTenantRole($this->tenantB, 'admin_tenant')
            ->create(['status' => 'active']);

        $response = $this->actingAs($user)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenantB->id])
            ->getJson('/api/reports/dashboard');

        $response->assertStatus(200);
        // Aunque su empresa primaria es A, la activa del switcher es B.
        $this->assertSame($this->tenantB->id, $response->json('tenant_id'));
    }

    public function test_non_root_cannot_scope_dashboard_to_foreign_tenant(): void
    {
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);

        // Pide una empresa a la que NO pertenece.
        $response = $this->actingAs($user)
            ->getJson('/api/reports/dashboard?tenant_id=' . $this->tenantC->id);

        $response->assertStatus(200);
        $this->assertSame(
            $this->tenantA->id,
            $response->json('tenant_id'),
            'Un no-root nunca debe scopear reportes a una empresa ajena'
        );
    }

    public function test_root_can_query_all_or_specific_tenant(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        // Sin parámetro: null = todas las empresas (root es global).
        $all = $this->actingAs($root)->getJson('/api/reports/dashboard');
        $all->assertStatus(200);
        $this->assertNull($all->json('tenant_id'));

        // Con parámetro: esa empresa, aunque root no pertenezca a ella.
        $specific = $this->actingAs($root)
            ->getJson('/api/reports/dashboard?tenant_id=' . $this->tenantB->id);
        $specific->assertStatus(200);
        $this->assertSame($this->tenantB->id, $specific->json('tenant_id'));
    }

    public function test_non_root_without_tenants_gets_sentinel_not_global_data(): void
    {
        // Usuario sin ninguna empresa: caso degenerado. getTenantId() debe dar
        // el centinela -1 (no matchea nada), NUNCA null, porque ReportsService
        // trata null como "todas las empresas" -> expondría la plataforma.
        $user = User::factory()->client()->create(['status' => 'active']);

        // Se afirma el contrato directamente sobre el helper: por el endpoint no
        // es observable, ya que el gate corta antes con 403 (ver más abajo) y el
        // cuerpo con 'tenant_id' nunca llega a emitirse.
        $controller = app(\App\Http\Controllers\Api\ReportsController::class);
        $method = new \ReflectionMethod($controller, 'getTenantId');
        $method->setAccessible(true);

        $tenantId = $method->invoke($controller, Request::create('/api/reports/dashboard'), $user);

        $this->assertSame(-1, $tenantId, 'Un no-root sin empresas nunca debe resolver a null (= todas)');

        // Y de punta a punta: sin empresa no hay rol en empresa -> sin ability
        // -> 403 fail-closed, que es aún más seguro que el centinela.
        $response = $this->actingAs($user)->getJson('/api/reports/dashboard');

        $response->assertStatus(403);
        $this->assertNull($response->json('tenant_id'));
    }

    // ==========================================
    // Gate de acceso (authorizeReports)
    // ==========================================

    public static function reportEndpointsProvider(): array
    {
        return [
            'documents' => ['/api/reports/documents'],
            'vacations' => ['/api/reports/vacations'],
            'users' => ['/api/reports/users'],
            'activity' => ['/api/reports/activity'],
        ];
    }

    #[DataProvider('reportEndpointsProvider')]
    public function test_all_report_endpoints_return_200_for_admin_tenant(string $endpoint): void
    {
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson($endpoint);

        $response->assertStatus(200);
        $this->assertSame($this->tenantA->id, $response->json('tenant_id'));
    }

    public function test_client_and_aprobador_are_forbidden_from_reports(): void
    {
        // Matriz de Accesos: cliente y aprobador NO acceden a reportes.
        foreach (['client', 'aprobador'] as $roleName) {
            $user = User::factory()
                ->withTenantRole($this->tenantA, $roleName, true)
                ->create(['status' => 'active']);

            $response = $this->actingAs($user)->getJson('/api/reports/dashboard');

            $response->assertStatus(403, "El rol {$roleName} no debe acceder a reportes");
        }
    }

    public function test_my_stats_still_accessible_for_client(): void
    {
        // my-stats opera sobre los datos propios del usuario: el gate de
        // reportes NO debe alcanzarlo (romperia a cliente y aprobador).
        $user = User::factory()
            ->withTenantRole($this->tenantA, 'client', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($user)->getJson('/api/reports/my-stats');

        $response->assertStatus(200);
    }
}
