<?php

namespace Tests\Unit\Services;

use App\Exceptions\UserCreationException;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;
    private User $admin;
    private Tenant $tenant;
    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->userService = new UserService(new AuditService());

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->clientRole = Role::where('name', 'client')->first();

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_create_user_with_valid_data(): void
    {
        $data = [
            'name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan@example.com',
            'role_id' => $this->clientRole->id,
            'document_type' => 'dni',
            'document_text' => '12345678',
            'tenant_id' => $this->tenant->id,
        ];

        $user = $this->userService->createUser($data, $this->admin, false);

        $this->assertEquals('Juan', $user->name);
        $this->assertEquals('Perez', $user->last_name);
        $this->assertEquals('juan@example.com', $user->email);
        $this->assertTrue($user->must_change_password);
        $this->assertEquals('client', $user->getCurrentRole());
        $this->assertCount(1, $user->tenants);
    }

    public function test_create_user_with_multiple_tenants_config(): void
    {
        $tenant2 = Tenant::factory()->create();
        $supervisor = User::factory()->client()->create();
        $supervisor->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $data = [
            'name' => 'Maria',
            'last_name' => 'Garcia',
            'email' => 'maria@example.com',
            'role_id' => $this->clientRole->id,
            'tenants_config' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'supervisor_id' => $supervisor->id,
                    'is_primary' => true,
                ],
                [
                    'tenant_id' => $tenant2->id,
                    'supervisor_id' => null,
                    'is_primary' => false,
                ],
            ],
        ];

        $user = $this->userService->createUser($data, $this->admin, false);

        $this->assertCount(2, $user->tenants);
        $primaryTenant = $user->tenants->where('pivot.is_primary', true)->first();
        $this->assertEquals($this->tenant->id, $primaryTenant->id);
    }

    public function test_create_user_assigns_orphan_documents(): void
    {
        $docType = DocumentType::factory()->create();

        $orphanDoc = Document::factory()->create([
            'user_id' => null,
            'tenant_id' => $this->tenant->id,
            'doc_type_id' => $docType->id,
            'employee_document_number' => '12345678',
            'status' => 'orphan',
            'uploaded_by' => $this->admin->id,
        ]);

        $data = [
            'name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan@example.com',
            'role_id' => $this->clientRole->id,
            'document_type' => 'dni',
            'document_text' => '12345678',
            'tenant_id' => $this->tenant->id,
        ];

        $user = $this->userService->createUser($data, $this->admin, false);

        $orphanDoc->refresh();
        $this->assertEquals($user->id, $orphanDoc->user_id);
        $this->assertNotEquals('orphan', $orphanDoc->status);
    }

    public function test_create_user_sends_welcome_email(): void
    {
        $data = [
            'name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ];

        $this->userService->createUser($data, $this->admin, true);

        // El correo se despacha vía SendWelcomeEmailJob (queue=sync en tests)
        // y se entrega con TenantMailerService::send(), que usa sendNow(); por
        // eso queda registrado como "sent" y no como "queued".
        Mail::assertSent(\App\Mail\WelcomeUserMail::class, function ($mail) {
            return $mail->hasTo('juan@example.com');
        });
    }

    public function test_update_user_basic_fields(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Juan',
            'last_name' => 'Perez',
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->actingAs($this->admin);

        $updatedUser = $this->userService->updateUser($user, [
            'name' => 'Juan Carlos',
            'phone' => '999888777',
        ]);

        $this->assertEquals('Juan Carlos', $updatedUser->name);
        $this->assertEquals('999888777', $updatedUser->phone);
    }

    public function test_update_user_changes_role(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $adminRole = Role::where('name', 'admin')->first();

        $this->actingAs($this->admin);

        $updatedUser = $this->userService->updateUser($user, [
            'role_id' => $adminRole->id,
        ]);

        $this->assertEquals('admin', $updatedUser->getCurrentRole());
    }

    public function test_update_user_to_root_removes_tenants(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $rootRole = Role::where('name', 'root')->first();

        $this->actingAs($this->admin);

        $updatedUser = $this->userService->updateUser($user, [
            'role_id' => $rootRole->id,
        ]);

        $this->assertEquals('root', $updatedUser->getCurrentRole());
        $this->assertCount(0, $updatedUser->tenants);
    }

    public function test_update_user_does_not_wipe_global_role_when_tenant_roles_empty(): void
    {
        // Simula un usuario "pre-backfill": tiene empresa y rol global en
        // user_roles (modelo viejo) pero SIN filas en user_tenant_roles.
        // El factory client() ya asigna el rol global 'client' en user_roles.
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->assertEquals('client', $user->fresh()->getCurrentRole());
        $this->assertSame(0, \App\Models\UserTenantRole::where('user_id', $user->id)->count());

        $this->actingAs($this->admin);

        // Edición que reenvía la empresa pero sin role_ids: no crea roles por
        // empresa, así que user_tenant_roles sigue vacío al llamar a
        // syncGlobalRoleFallback.
        $this->userService->updateUser($user, [
            'tenants_config' => [
                ['tenant_id' => $this->tenant->id, 'is_primary' => true, 'role_ids' => []],
            ],
        ]);

        // Guard anti-erosión: el rol global debe preservarse. Antes del fix,
        // syncGlobalRoleFallback hacía sync([]) y lo borraba, dejando al usuario
        // sin rol en los usos que aún leen user_roles (display y filtrado por
        // fila de documentos).
        $this->assertEquals('client', $user->fresh()->getCurrentRole());
    }

    public function test_generate_temporary_password_has_correct_length(): void
    {
        $password = $this->userService->generateTemporaryPassword();

        $this->assertEquals(12, strlen($password));
    }

    // ── canManageUser (Decisión C1 — reemplaza a la vieja canAccessUser,
    // que solo miraba solapamiento de tenant sin ninguna jerarquía de rol) ──

    public function test_can_manage_user_root_can_manage_all(): void
    {
        $root = User::factory()->root()->create();
        $user = User::factory()->client()->create();

        $this->assertTrue($this->userService->canManageUser($root, $user));
    }

    public function test_can_manage_user_admin_manages_client_in_same_tenant(): void
    {
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)->create();
        $client = User::factory()->withTenantRole($this->tenant, 'client', true)->create();

        $this->assertTrue($this->userService->canManageUser($admin, $client));
    }

    public function test_can_manage_user_admin_manages_aprobador_in_same_tenant(): void
    {
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)->create();
        $aprobador = User::factory()->withTenantRole($this->tenant, 'aprobador', true)->create();

        $this->assertTrue($this->userService->canManageUser($admin, $aprobador));
    }

    public function test_admin_cannot_manage_another_admin_in_same_tenant(): void
    {
        // 'admin' solo administra aprobador/client (MANAGEABLE_ROLES), no a
        // un par.
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)->create();
        $peer = User::factory()->withTenantRole($this->tenant, 'admin', true)->create();

        $this->assertFalse($this->userService->canManageUser($admin, $peer));
    }

    public function test_admin_tenant_manages_admin_in_same_tenant(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)->create();
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)->create();

        $this->assertTrue($this->userService->canManageUser($adminTenant, $admin));
    }

    public function test_admin_tenant_cannot_manage_another_admin_tenant_anywhere(): void
    {
        // Núcleo de C1: un admin_tenant es intocable para otro no-root,
        // incluso sin empresa compartida — el chequeo del rol del target es
        // global ("en CUALQUIER empresa"), no por tenant.
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $actor = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)->create();
        $target = User::factory()->withTenantRole($otherTenant, 'admin_tenant', true)->create();

        $this->assertFalse($this->userService->canManageUser($actor, $target));
    }

    public function test_admin_tenant_cannot_manage_self(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)->create();

        $this->assertFalse($this->userService->canManageUser($adminTenant, $adminTenant));
    }

    public function test_nobody_non_root_can_manage_root(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)->create();
        $root = User::factory()->root()->create();

        $this->assertFalse($this->userService->canManageUser($adminTenant, $root));
    }

    public function test_aprobador_cannot_manage_anyone(): void
    {
        // 'aprobador' no está en MANAGEABLE_ROLES: no administra a nadie,
        // aunque comparta tenant.
        $aprobador = User::factory()->withTenantRole($this->tenant, 'aprobador', true)->create();
        $client = User::factory()->withTenantRole($this->tenant, 'client', true)->create();

        $this->assertFalse($this->userService->canManageUser($aprobador, $client));
    }

    public function test_cannot_manage_user_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $otherAdmin = User::factory()->withTenantRole($otherTenant, 'admin', true)->create();
        $client = User::factory()->withTenantRole($this->tenant, 'client', true)->create();

        $this->assertFalse($this->userService->canManageUser($otherAdmin, $client));
    }

    public function test_assign_orphan_documents_returns_count(): void
    {
        $docType = DocumentType::factory()->create();

        // Create orphan documents with unique periods to avoid constraint violation
        foreach (['2025-01', '2025-02', '2025-03'] as $period) {
            Document::factory()->create([
                'user_id' => null,
                'tenant_id' => $this->tenant->id,
                'doc_type_id' => $docType->id,
                'employee_document_number' => '12345678',
                'period' => $period,
                'status' => 'orphan',
                'uploaded_by' => $this->admin->id,
            ]);
        }

        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $count = $this->userService->assignOrphanDocuments($user, '12345678', $this->tenant->id);

        $this->assertEquals(3, $count);
    }

    public function test_assign_orphan_documents_returns_zero_when_none(): void
    {
        $user = User::factory()->client()->create();

        $count = $this->userService->assignOrphanDocuments($user, '12345678', $this->tenant->id);

        $this->assertEquals(0, $count);
    }
}
