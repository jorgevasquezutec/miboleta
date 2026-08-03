<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-07]: el cliente pidió retirar 'admin_tenant' de la carga
 * masiva de usuarios para TODOS los actores. UsersImport/ValidationRulesSheet
 * ya lo rechazaban en el flujo por archivo (Excel); este test cubre la
 * "puerta de atrás" que se encontró en el flujo de datos ya editados (grid ->
 * JSON vía POST /api/user-batches/upload-data): UploadUserBatchDataRequest
 * seguía aceptando 'admin_tenant' en organizaciones.*.roles.* aunque el
 * <select> del frontend ya no lo ofrezca (nada impide un payload manual/API
 * directo).
 *
 * [OBS-CLIENTE 2026-08]: el cliente aclaró que root SÍ debe poder asignar
 * 'admin_tenant' por esta vía (admin_tenant/admin siguen sin poder). Ver
 * BulkUserUploadService::allowedOrgRolesFor, fuente única que ahora depende
 * del actor autenticado en la request.
 */
class UploadUserBatchDataRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminTenant;
    private User $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->adminTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $this->root = User::factory()->root()->create(['status' => 'active']);
    }

    private function payloadWithRole(array $roles): array
    {
        return [
            'send_welcome_emails' => false,
            'users' => [
                [
                    'nombre' => 'Ana',
                    'apellido' => 'Torres',
                    'email' => 'ana.torres@example.com',
                    'tipo_documento' => 'dni',
                    'numero_documento' => '12345678',
                    'estado' => 'active',
                    'organizaciones' => [
                        [
                            'ruc' => $this->tenant->ruc,
                            'roles' => $roles,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_upload_data_rejects_admin_tenant_role_in_payload(): void
    {
        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $this->payloadWithRole(['admin_tenant']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['users.0.organizaciones.0.roles.0']);

        // Los errores vienen planos, con la ruta con puntos como clave literal
        // (formato estándar de Laravel), no anidados: hay que indexar así en
        // vez de con json('errors.users.0...'), que interpretaría los puntos
        // como una ruta anidada y no encontraría nada.
        $message = $response->json('errors')['users.0.organizaciones.0.roles.0'][0];
        $this->assertStringContainsString('admin, client, aprobador', $message);

        $this->assertDatabaseMissing('users', ['email' => 'ana.torres@example.com']);
    }

    public function test_upload_data_accepts_allowed_role_in_payload(): void
    {
        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $this->payloadWithRole(['aprobador']));

        $response->assertStatus(201);
    }

    /**
     * [OBS-CLIENTE 2026-08]: root sí puede asignar 'admin_tenant' por carga
     * masiva; solo admin_tenant/admin siguen sin poder (test de arriba). El
     * lote se procesa de forma síncrona (QUEUE_CONNECTION=sync en testing,
     * ver UserBatchProcessingTest), así que se puede afirmar directamente
     * que el usuario quedó creado con el rol admin_tenant en la empresa.
     */
    public function test_upload_data_accepts_admin_tenant_role_when_actor_is_root(): void
    {
        $response = $this->actingAs($this->root)
            ->postJson('/api/user-batches/upload-data', $this->payloadWithRole(['admin_tenant']));

        $response->assertStatus(201);

        $user = User::where('email', 'ana.torres@example.com')->first();
        $this->assertNotNull($user, 'El usuario debió crearse');
        $this->assertTrue(
            $user->hasRoleInTenant('admin_tenant', $this->tenant->id),
            'El usuario debió quedar con el rol admin_tenant en la empresa'
        );
    }
}
