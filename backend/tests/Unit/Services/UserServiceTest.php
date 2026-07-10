<?php

namespace Tests\Unit\Services;

use App\Exceptions\UserCreationException;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
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

        $this->userService = new UserService();

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

    public function test_generate_temporary_password_has_correct_length(): void
    {
        $password = $this->userService->generateTemporaryPassword();

        $this->assertEquals(12, strlen($password));
    }

    public function test_can_access_user_root_can_access_all(): void
    {
        $root = User::factory()->root()->create();
        $user = User::factory()->client()->create();

        $canAccess = $this->userService->canAccessUser($root, $user);

        $this->assertTrue($canAccess);
    }

    public function test_can_access_user_admin_same_tenant(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $canAccess = $this->userService->canAccessUser($this->admin, $user);

        $this->assertTrue($canAccess);
    }

    public function test_cannot_access_user_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAdmin = User::factory()->admin()->create();
        $otherAdmin->tenants()->attach($otherTenant->id, ['is_primary' => true]);

        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $canAccess = $this->userService->canAccessUser($otherAdmin, $user);

        $this->assertFalse($canAccess);
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
