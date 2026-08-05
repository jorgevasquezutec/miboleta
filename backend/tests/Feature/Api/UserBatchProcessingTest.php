<?php

namespace Tests\Feature\Api;

use App\Jobs\ProcessUserChunk;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * FASE 4 (OBS-CLIENTE 2026-07, D4/D5): status real del batch y semántica
 * "parcial correcta por fila" (no todo-o-nada). QUEUE_CONNECTION=sync en
 * testing (phpunit.xml) hace que Bus::batch(...)->dispatch() -y sus
 * callbacks then/catch/finally- corran de forma síncrona dentro del mismo
 * request, así que se puede probar el ciclo completo (POST -> chunk ->
 * status final) sin colas reales.
 */
class UserBatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->adminTenant = User::factory()
            ->admin()
            ->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
    }

    private function userRow(string $nombre, string $documento, string $email, array $roles = ['client']): array
    {
        return [
            'nombre' => $nombre,
            'apellido' => 'Apellido',
            'email' => $email,
            'tipo_documento' => 'dni',
            'numero_documento' => $documento,
            'estado' => 'active',
            'organizaciones' => [
                [
                    'ruc' => $this->tenant->ruc,
                    'roles' => $roles,
                ],
            ],
        ];
    }

    public function test_mixed_chunk_produces_partial_status_with_valid_row_persisted_and_role_in_error(): void
    {
        // Fila que ya existe (mismo documento que un usuario pre-creado) ->
        // findExistingUser() la rechaza; la otra fila es válida.
        $existing = User::factory()->create(['document_text' => '99999999']);

        $payload = [
            'send_welcome_emails' => false,
            'users' => [
                $this->userRow('Valido', '11112222', 'valido@example.com', ['client']),
                $this->userRow('Duplicado', '99999999', 'duplicado@example.com', ['aprobador']),
            ],
        ];

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $payload);

        $response->assertStatus(201);

        $uuid = $response->json('batch.uuid');
        $batch = UserBatch::where('uuid', $uuid)->first();

        $this->assertNotNull($batch);
        $this->assertSame('partial', $batch->status);
        $this->assertSame(1, $batch->created_users);
        $this->assertSame(1, $batch->failed_rows);
        // processed_rows cuenta creadas + fallidas (updateBatchCounters), no
        // solo las creadas: con 1 fila fallida de 2, debe ser 2, no 1.
        $this->assertSame(2, $batch->processed_rows);

        // La fila válida SÍ quedó persistida (D5: transacción por fila, no
        // por chunk completo).
        $this->assertDatabaseHas('users', ['email' => 'valido@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'duplicado@example.com']);

        // error_summary trae el detalle por fila, con el campo 'rol' poblado
        // (antes BulkUploadErrors.tsx mostraba siempre "-").
        $this->assertIsArray($batch->error_summary);
        $this->assertCount(1, $batch->error_summary);
        $this->assertSame('aprobador', $batch->error_summary[0]['rol']);
        $this->assertSame('99999999', $batch->error_summary[0]['documento']);

        // Visible en el filtro "Parcial" del historial.
        $listResponse = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches?status=partial');
        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id');
        $this->assertContains($batch->id, $ids->toArray());
    }

    public function test_all_rows_invalid_produces_failed_status_and_zero_users_created(): void
    {
        $existing1 = User::factory()->create(['document_text' => '10101010']);
        $existing2 = User::factory()->create(['document_text' => '20202020']);

        $payload = [
            'send_welcome_emails' => false,
            'users' => [
                $this->userRow('Duplicado1', '10101010', 'dup1@example.com'),
                $this->userRow('Duplicado2', '20202020', 'dup2@example.com'),
            ],
        ];

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $payload);

        $response->assertStatus(201);

        $uuid = $response->json('batch.uuid');
        $batch = UserBatch::where('uuid', $uuid)->first();

        $this->assertNotNull($batch);
        $this->assertSame('failed', $batch->status);
        $this->assertSame(0, $batch->created_users);
        $this->assertSame(2, $batch->failed_rows);

        $listResponse = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/user-batches?status=failed');
        $listResponse->assertStatus(200);
        $ids = collect($listResponse->json('data'))->pluck('id');
        $this->assertContains($batch->id, $ids->toArray());
    }

    /**
     * Defensa en profundidad (hallazgo de Fase 5): ProcessUserChunk::
     * resolveRoleIdsByNames debe rechazar cualquier rol fuera de
     * BulkUserUploadService::allowedOrgRolesFor($creator), no solo 'root'. El
     * camino HTTP normal (upload-data) ya bloquea 'admin_tenant' en la
     * validación del FormRequest cuando el creador NO es root, así que para
     * probar el blindaje del WORKER hay que llegar a él sin pasar por esa
     * validación: se construye el UserBatch y se despacha ProcessUserChunk
     * directamente, con 'organizaciones' ya en el formato interno (tenant_id
     * resuelto) que espera el job. Este batch lo crea $this->adminTenant
     * (NO root), así que 'admin_tenant' sigue sin estar permitido.
     */
    public function test_role_not_in_allowed_org_roles_is_rejected_as_row_error(): void
    {
        $batch = UserBatch::create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $this->adminTenant->id,
            'original_filename' => 'test.json',
            'file_size' => 0,
            'status' => 'pending',
            'total_rows' => 1,
            'processing_options' => ['send_welcome_emails' => false],
        ]);

        $userData = [
            'nombre' => 'Colado',
            'apellido' => 'Rol',
            'email' => 'colado@example.com',
            'tipo_documento' => 'dni',
            'numero_documento' => '55556666',
            'estado' => 'active',
            'organizaciones' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'roles' => ['admin_tenant'],
                ],
            ],
        ];

        Bus::batch([new ProcessUserChunk($batch->uuid, [$userData], 1)])
            ->onQueue('bulk-uploads')
            ->dispatch();

        $batch->refresh();

        $this->assertSame(0, $batch->created_users);
        $this->assertSame(1, $batch->failed_rows);
        $this->assertDatabaseMissing('users', ['email' => 'colado@example.com']);

        $this->assertIsArray($batch->error_summary);
        $this->assertCount(1, $batch->error_summary);
        $this->assertSame('admin_tenant', $batch->error_summary[0]['rol']);
        $this->assertStringContainsString('no permitido', $batch->error_summary[0]['error']);
    }

    /**
     * [OBS-CLIENTE 2026-08]: contraparte del test anterior. Cuando el batch
     * lo crea un root ($batch->created_by_user_id apunta a un root),
     * ProcessUserChunk::resolveRoleIdsByNames debe permitir 'admin_tenant' y
     * crear el usuario con ese rol en la empresa (no un error de fila).
     */
    public function test_admin_tenant_role_is_accepted_when_batch_creator_is_root(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        $batch = UserBatch::create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $root->id,
            'original_filename' => 'test.json',
            'file_size' => 0,
            'status' => 'pending',
            'total_rows' => 1,
            'processing_options' => ['send_welcome_emails' => false],
        ]);

        $userData = [
            'nombre' => 'Aceptado',
            'apellido' => 'Rol',
            'email' => 'aceptado.root@example.com',
            'tipo_documento' => 'dni',
            'numero_documento' => '66667777',
            'estado' => 'active',
            'organizaciones' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'roles' => ['admin_tenant'],
                ],
            ],
        ];

        Bus::batch([new ProcessUserChunk($batch->uuid, [$userData], 1)])
            ->onQueue('bulk-uploads')
            ->dispatch();

        $batch->refresh();

        $this->assertSame(1, $batch->created_users);
        $this->assertSame(0, $batch->failed_rows);

        $user = User::where('email', 'aceptado.root@example.com')->first();
        $this->assertNotNull($user, 'El usuario debió crearse');
        $this->assertTrue(
            $user->hasRoleInTenant('admin_tenant', $this->tenant->id),
            'El usuario debió quedar con el rol admin_tenant en la empresa'
        );
    }

    /**
     * [Área 1] UserBatch::syncInitialEmployeeCounts ahora delega en
     * Tenant::employeesQuery() (rol de empleado por tenant), no en
     * $tenant->users()->count(): $this->adminTenant del setUp es miembro de
     * la empresa (user_tenants) con rol 'admin_tenant', que NO cuenta como
     * empleado. El conteo inicial fijado tras este batch debe reflejar solo
     * los 2 empleados (client + aprobador) recién creados, no 3.
     */
    public function test_sync_initial_employee_counts_only_counts_org_employee_roles(): void
    {
        $this->assertSame(0, $this->tenant->fresh()->initial_employee_count);

        $payload = [
            'send_welcome_emails' => false,
            'users' => [
                $this->userRow('Empleado1', '11112222', 'empleado1@example.com', ['client']),
                $this->userRow('Empleado2', '33334444', 'empleado2@example.com', ['aprobador']),
            ],
        ];

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $payload);

        $response->assertStatus(201);

        $this->assertSame(2, $this->tenant->fresh()->initial_employee_count);
    }

    /**
     * [Área 1] syncInitialEmployeeCounts sigue siendo idempotente con la
     * fórmula filtrada: si el tenant ya tiene un initial_employee_count
     * fijado (no vacío), un batch posterior no debe pisarlo aunque el nuevo
     * conteo filtrado sea distinto.
     */
    public function test_sync_initial_employee_counts_is_idempotent(): void
    {
        $this->tenant->update(['initial_employee_count' => 5]);

        $payload = [
            'send_welcome_emails' => false,
            'users' => [
                $this->userRow('Empleado3', '55556666', 'empleado3@example.com', ['client']),
            ],
        ];

        $response = $this->actingAs($this->adminTenant)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->postJson('/api/user-batches/upload-data', $payload);

        $response->assertStatus(201);

        // Ya estaba fijado en 5: no se toca aunque el conteo filtrado real
        // (1 empleado) sea distinto.
        $this->assertSame(5, $this->tenant->fresh()->initial_employee_count);
    }
}
