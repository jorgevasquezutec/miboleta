<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $root;
    private User $admin;
    private User $client;
    private Tenant $tenant;
    private Role $clientRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        $this->clientRole = Role::where('name', 'client')->first();

        $this->root = User::factory()->root()->create(['status' => 'active']);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        // Los permisos operativos ahora se resuelven por empresa (user_tenant_roles),
        // no por el rol global. Sin esta asignación el admin no pasa el chequeo
        // hasRoleInTenant('admin', ...) de StoreUserRequest.
        \App\Models\UserTenantRole::create([
            'user_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => Role::where('name', 'admin')->first()->id,
        ]);

        $this->client = User::factory()->client()->create(['status' => 'active']);
        $this->client->tenants()->attach($this->tenant->id, ['is_primary' => true]);
        \App\Models\UserTenantRole::create([
            'user_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
            'role_id' => $this->clientRole->id,
        ]);
    }

    public function test_root_can_list_all_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->root)->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'status'],
                ],
                'meta',
            ]);
    }

    /**
     * Regresión: UsersListPage manda siempre `search` y `status` en la query
     * string (vacíos mientras no se filtre nada) y ConvertEmptyStringsToNull
     * los convierte en null. index() usaba has('search'), que es true para
     * una clave presente aunque valga null, y le pasaba ese null a
     * User::scopeMatchingFullName (tipado string): 500 al abrir Gestión de
     * Usuarios. Un filtro vacío significa "no filtrar", no un error.
     */
    public function test_index_ignores_empty_search_and_status_filters(): void
    {
        User::factory()->count(3)->create();

        $sinFiltros = $this->actingAs($this->root)->getJson('/api/users');
        $sinFiltros->assertStatus(200);

        $response = $this->actingAs($this->root)
            ->getJson('/api/users?page=1&per_page=10&search=&status=');

        $response->assertStatus(200);
        $this->assertSame($sinFiltros->json('meta.total'), $response->json('meta.total'));
    }

    /**
     * Mismo patrón que el search vacío, sin llegar a 500: el default de
     * get('per_page', 10) solo aplica cuando la clave falta, así que un
     * `?per_page=` vacío (null tras ConvertEmptyStringsToNull) hacía
     * paginate(null) y devolvía páginas de 15 (el perPage del modelo) en vez
     * de las 10 que promete el controller.
     */
    public function test_index_falls_back_to_default_per_page_when_param_is_empty(): void
    {
        User::factory()->count(3)->create();

        $response = $this->actingAs($this->root)->getJson('/api/users?per_page=');

        $response->assertStatus(200);
        $this->assertSame(10, $response->json('meta.per_page'));
    }

    public function test_admin_can_list_tenant_users(): void
    {
        User::factory()->count(3)->create()->each(function ($user) {
            $user->tenants()->attach($this->tenant->id);
        });

        $response = $this->actingAs($this->admin)->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_client_cannot_list_users(): void
    {
        // E1 [seguridad]: 'users.view_list' = [root, admin, admin_tenant].
        // 'client' no aparece — antes este endpoint no llamaba a authorize()
        // en ningún punto y cualquier autenticado podía listar usuarios.
        $response = $this->actingAs($this->client)->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_index_hides_requester_and_admin_tenant_rows_for_non_root(): void
    {
        // Decisión C1: el listado de Gestión de Usuarios oculta, para
        // no-root, la fila propia (la cuenta propia se administra desde
        // /profile) y cualquier usuario con rol admin_tenant en cualquier
        // empresa (solo root administra cuentas admin_tenant).
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant')
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->getJson('/api/users');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertNotContains($this->admin->id, $ids, 'El admin no debe verse a sí mismo en el listado.');
        $this->assertNotContains($adminTenant->id, $ids, 'Ningún admin_tenant debe aparecer para un no-root.');
        $this->assertContains($this->client->id, $ids, 'Un client normal sí debe seguir apareciendo.');
    }

    public function test_index_shows_everyone_including_admin_tenant_for_root(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant')
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->root)->getJson('/api/users');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($adminTenant->id, $ids);
    }

    // ==========================================
    // B3: vacation_balance en el listado (empresa activa)
    // ==========================================

    public function test_index_includes_vacation_balance_for_non_root_active_tenant(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        // 2 años completos de servicio -> accrued = 60; initial = 10.
        $this->client->tenants()->updateExistingPivot($this->tenant->id, [
            'hire_date' => $now->copy()->subYears(2)->format('Y-m-d'),
            'vacation_balance_initial' => 10,
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/users');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('id', $this->client->id);

        $this->assertNotNull($row);
        $this->assertNotNull($row['vacation_balance']);
        // assertEquals (no assertSame): un float entero como 70.0 se
        // serializa a JSON como `70` y json_decode() lo lee como int, no
        // hay distinción int/float del lado del cliente (JS) tampoco.
        $this->assertEquals(70.0, $row['vacation_balance']['pending']); // 10 + 2*30
        $this->assertEquals(0.0, $row['vacation_balance']['taken']);
        $this->assertSame($this->tenant->id, $row['vacation_balance']['tenant_id']);

        Carbon::setTestNow();
    }

    public function test_index_vacation_balance_is_null_when_root_has_no_active_tenant(): void
    {
        // Root sin header ni ?tenant_id: modo "todas las empresas". El saldo
        // por usuario es ambiguo (podría pertenecer a varias empresas), así
        // que no se calcula (null), nunca una suma inventada entre empresas.
        $response = $this->actingAs($this->root)->getJson('/api/users');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('id', $this->client->id);

        $this->assertNotNull($row);
        $this->assertNull($row['vacation_balance']);
    }

    public function test_index_includes_vacation_balance_for_root_with_explicit_tenant_id(): void
    {
        $now = Carbon::parse('2026-07-31')->startOfDay();
        Carbon::setTestNow($now);

        $this->client->tenants()->updateExistingPivot($this->tenant->id, [
            'hire_date' => $now->copy()->subYears(1)->format('Y-m-d'),
            'vacation_balance_initial' => 0,
        ]);

        $response = $this->actingAs($this->root)
            ->getJson('/api/users?tenant_id=' . $this->tenant->id);

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('id', $this->client->id);

        $this->assertNotNull($row);
        $this->assertNotNull($row['vacation_balance']);
        $this->assertEquals(30.0, $row['vacation_balance']['pending']); // 0 + 1*30

        Carbon::setTestNow();
    }

    public function test_index_vacation_balance_does_not_break_without_hire_date(): void
    {
        // El client de setUp() no tiene hire_date asignado.
        $response = $this->actingAs($this->admin)
            ->withHeaders(['X-Tenant-Ids' => (string) $this->tenant->id])
            ->getJson('/api/users');

        $response->assertStatus(200);
        $row = collect($response->json('data'))->firstWhere('id', $this->client->id);

        $this->assertNotNull($row);
        $this->assertNotNull($row['vacation_balance']);
        $this->assertEquals(0.0, $row['vacation_balance']['pending']);
        $this->assertEquals(0.0, $row['vacation_balance']['balance']);
    }

    public function test_root_can_view_any_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->root)->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $user->id]);
    }

    public function test_admin_can_view_tenant_user(): void
    {
        $response = $this->actingAs($this->admin)->getJson("/api/users/{$this->client->id}");

        $response->assertStatus(200);
    }

    public function test_admin_cannot_view_user_from_different_tenant(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($this->admin)->getJson("/api/users/{$otherUser->id}");

        $response->assertStatus(403);
    }

    public function test_root_can_create_user(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Nuevo']);

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
    }

    public function test_admin_cannot_create_user_in_tenant(): void
    {
        // Matriz de Accesos: crear usuarios es de root ('users.create_any_role')
        // y admin_tenant ('users.create_limited_role'). 'admin' no aparece, así
        // que ya no puede — antes este test afirmaba lo contrario.
        $response = $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_admin_tenant_can_create_user_in_tenant(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_admin_tenant_can_create_user_with_admin_role(): void
    {
        // Observación 1: "asignar" (StoreUserRequest/assignableRoleNamesFor,
        // ASSIGNABLE_ROLES) y "administrar" (canManageUser, MANAGEABLE_ROLES)
        // son jerarquías distintas. Un admin_tenant sigue pudiendo dar de
        // alta un usuario con rol admin; lo que ya no puede es administrar
        // esa cuenta una vez creada (ver test_admin_tenant_cannot_update_admin_user).
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $adminRole = Role::where('name', 'admin')->first();

        $response = $this->actingAs($adminTenant)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Admin',
            'email' => 'nuevo-admin@example.com',
            'role_id' => $adminRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'nuevo-admin@example.com']);
    }

    public function test_create_user_requires_email(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_requires_unique_email(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'email' => $this->client->email,
            'role_id' => $this->clientRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Guard de entrada: un hire_date futuro (dato mal tipeado) producía
     * años de servicio negativos en VacationBalanceService (ver guard
     * defensivo ahí). Se rechaza aquí, en el alta manual, además del guard
     * del servicio.
     */
    public function test_create_user_rejects_future_hire_date(): void
    {
        $response = $this->actingAs($this->root)->postJson('/api/users', [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'tenants_config' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'role_ids' => [$this->clientRole->id],
                    'hire_date' => now()->addDay()->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenants_config.0.hire_date']);
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.com']);
    }

    public function test_root_can_update_user(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id);

        $response = $this->actingAs($this->root)->putJson("/api/users/{$user->id}", [
            'name' => 'Actualizado',
            'phone' => '999888777',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Actualizado']);
    }

    /**
     * Mismo guard de entrada que en el alta, para la edición manual.
     */
    public function test_update_user_rejects_future_hire_date(): void
    {
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $response = $this->actingAs($this->root)->putJson("/api/users/{$user->id}", [
            'tenants_config' => [
                [
                    'tenant_id' => $this->tenant->id,
                    'role_ids' => [$this->clientRole->id],
                    'hire_date' => now()->addMonth()->format('Y-m-d'),
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenants_config.0.hire_date']);
    }

    public function test_admin_can_update_client_in_own_tenant(): void
    {
        // [OBS-CLIENTE 2026-07] C3: 'users.update' ahora incluye 'admin'.
        // Antes este test afirmaba lo contrario (admin no podía editar a
        // nadie); ese era exactamente el bug que reportó el cliente.
        $response = $this->actingAs($this->admin)->putJson("/api/users/{$this->client->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Actualizado']);
    }

    public function test_admin_can_update_aprobador_in_own_tenant(): void
    {
        $aprobador = User::factory()->withTenantRole($this->tenant, 'aprobador', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->putJson("/api/users/{$aprobador->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_cannot_update_another_admin_in_own_tenant(): void
    {
        // C3 solo habilita a 'admin' para administrar aprobador/client
        // (UserService::MANAGEABLE_ROLES): un par 'admin' sigue fuera de su
        // alcance.
        $peer = User::factory()->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->putJson("/api/users/{$peer->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['id' => $peer->id, 'name' => 'Actualizado']);
    }

    public function test_admin_cannot_update_admin_tenant_user(): void
    {
        // C1 regla 3: un admin_tenant es intocable para cualquier no-root,
        // aunque comparta empresa.
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($this->admin)->putJson("/api/users/{$adminTenant->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_can_update_tenant_user(): void
    {
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/users/{$this->client->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_tenant_cannot_update_admin_user(): void
    {
        // OBS-CLIENTE 2026-08 (Observación 1): antes de esto un admin_tenant
        // SÍ podía editar una cuenta admin en la empresa compartida (misma
        // jerarquía que la de asignar el rol). Ahora solo root administra
        // admins; admin_tenant conserva la potestad de ASIGNAR el rol admin
        // al crear/editar (ver test_admin_tenant_can_create_user_with_admin_role),
        // pero no la de administrar la cuenta ya creada.
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/users/{$admin->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['id' => $admin->id, 'name' => 'Actualizado']);
    }

    public function test_admin_tenant_cannot_update_another_admin_tenant(): void
    {
        // C1: solo root administra cuentas admin_tenant.
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $peerAdminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/users/{$peerAdminTenant->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_reset_password_of_admin_user(): void
    {
        // Observación 1: adminResetPassword (PasswordController, ver
        // cobertura hermana en PasswordControllerTest) usa el mismo
        // UserService::canManageUser() que update()/destroy(); esta prueba
        // confirma aquí, junto al resto de casos de admin_tenant vs. admin,
        // que ahora también bloquea target admin (antes solo bloqueaba
        // target admin_tenant/root).
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $admin = User::factory()->withTenantRole($this->tenant, 'admin', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$admin->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_update_self(): void
    {
        // C1 regla 2: nadie se administra a sí mismo desde Gestión de
        // Usuarios (el perfil propio va por /profile).
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->putJson("/api/users/{$adminTenant->id}", [
            'name' => 'Actualizado',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_can_still_view_own_profile_readonly(): void
    {
        // Excepción de solo-lectura de show(): aunque canManageUser()
        // bloquea la auto-administración, el propio detalle sigue siendo
        // visible (no rompe deep-links al perfil propio).
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->getJson("/api/users/{$adminTenant->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $adminTenant->id]);
    }

    public function test_admin_cannot_assign_admin_role_when_updating_client(): void
    {
        // C3: assignableRoleNamesFor ya limitaba a 'admin' a asignar solo
        // aprobador/client; ahora que 'admin' alcanza este endpoint
        // (users.update), ese límite es lo único que lo frena de escalar a
        // un client a admin.
        $adminRole = Role::where('name', 'admin')->first();

        $response = $this->actingAs($this->admin)->putJson("/api/users/{$this->client->id}", [
            'role_id' => $adminRole->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_root_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->root)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);
        // User model uses SoftDeletes
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_tenant_user(): void
    {
        // Item 28: solo root puede eliminar usuarios (ability 'users.delete' de
        // la Matriz de Accesos). Admin y admin_tenant ya no pueden, aunque
        // compartan tenant con el usuario objetivo.
        $user = User::factory()->client()->create();
        $user->tenants()->attach($this->tenant->id);

        $response = $this->actingAs($this->admin)->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_tenant_cannot_delete_tenant_user(): void
    {
        // C2: 'users.delete' = [root] únicamente. Confirma que un
        // admin_tenant (que sí puede editar) no puede eliminar, ni siquiera
        // a un usuario que sí administra.
        $adminTenant = User::factory()->withTenantRole($this->tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->deleteJson("/api/users/{$this->client->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $this->client->id]);
    }

    public function test_root_cannot_delete_himself(): void
    {
        // La ability 'users.delete' permite a root, pero nadie puede eliminarse
        // a sí mismo: esa regla es del par (actor, objetivo) y vive en
        // UserController::destroy(), no en la matriz.
        $response = $this->actingAs($this->root)->deleteJson("/api/users/{$this->root->id}");

        $response->assertStatus(403);
        $this->assertNotSoftDeleted('users', ['id' => $this->root->id]);
    }

    public function test_filter_users_by_search(): void
    {
        User::factory()->create([
            'name' => 'Juan',
            'email' => 'juan@test.com',
        ]);

        $response = $this->actingAs($this->root)->getJson('/api/users?search=juan');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(1, count($data));
    }

    /**
     * Regresión: antes 'name' y 'last_name' se comparaban por separado
     * contra el término completo, así que buscar "Juan Pérez" no
     * encontraba al empleado con name=Juan, last_name=Pérez (ninguna
     * columna contiene la cadena completa). Ver User::scopeMatchingFullName.
     */
    public function test_filter_users_by_full_name_search(): void
    {
        User::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
            'email' => 'juanperez@test.com',
        ]);
        User::factory()->create([
            'name' => 'Luis',
            'last_name' => 'Ramos',
            'email' => 'luisramos@test.com',
        ]);

        $response = $this->actingAs($this->root)->getJson('/api/users?search=' . urlencode('Juan Pérez'));

        $response->assertStatus(200);
        $emails = collect($response->json('data'))->pluck('email');
        $this->assertTrue($emails->contains('juanperez@test.com'));
        $this->assertFalse($emails->contains('luisramos@test.com'));
    }

    public function test_filter_users_by_status(): void
    {
        User::factory()->inactive()->create();

        $response = $this->actingAs($this->root)->getJson('/api/users?status=inactive');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_users(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(401);
    }
}
