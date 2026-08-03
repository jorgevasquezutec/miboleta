<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->user = User::factory()->client()->create([
            'password' => Hash::make('oldpassword'),
            'must_change_password' => false,
            'status' => 'active',
        ]);
    }

    public function test_user_can_request_password_reset(): void
    {
        $response = $this->postJson('/api/password/forgot', [
            'email' => $this->user->email,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
        ]);

        Mail::assertQueued(\App\Mail\ForgotPasswordMail::class);
    }

    public function test_forgot_password_returns_ok_for_nonexistent_email(): void
    {
        // For security, we don't reveal if email exists
        $response = $this->postJson('/api/password/forgot', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(200);
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $token = 'valid-test-token';

        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'email' => $this->user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/password/reset', [
            'email' => $this->user->email,
            'token' => 'wrong-token',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // API returns 422 for invalid token
        $response->assertStatus(422);
    }

    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/password/change', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/password/change', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        // API returns 422 for incorrect current password
        $response->assertStatus(422);
    }

    public function test_change_password_requires_confirmation(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/password/change', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
        // CustomFormRequest returns {message: "error"} format
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_force_change_password_when_required(): void
    {
        $this->user->update(['must_change_password' => true]);

        $response = $this->actingAs($this->user)->postJson('/api/password/force-change', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);

        $user = $this->user->fresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }

    public function test_force_change_fails_when_not_required(): void
    {
        $this->user->update(['must_change_password' => false]);

        $response = $this->actingAs($this->user)->postJson('/api/password/force-change', [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(400);
    }

    public function test_unauthenticated_cannot_change_password(): void
    {
        $response = $this->postJson('/api/password/change', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(401);
    }

    // ── adminResetPassword: hallazgo de seguridad fuera del pedido del
    // cliente (hermano de C1). Antes, este endpoint solo chequeaba la
    // ability 'users.reset_password' (root, admin_tenant) sin mirar el
    // usuario OBJETIVO en ningún punto: cualquier admin_tenant podía
    // resetear la contraseña de CUALQUIER usuario por id arbitrario,
    // incluido otro admin_tenant o un root — toma de control de cuenta, no
    // solo fuga de datos. Ahora aplica UserService::canManageUser(), mismo
    // patrón que UserController::update(). ──

    public function test_root_can_reset_password_of_any_user(): void
    {
        $root = User::factory()->root()->create(['status' => 'active']);

        $response = $this->actingAs($root)->postJson("/api/users/{$this->user->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_tenant_can_reset_password_of_client_in_own_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $adminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $client = User::factory()->withTenantRole($tenant, 'client', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$client->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(200);
    }

    public function test_admin_tenant_cannot_reset_password_of_user_in_tenant_it_does_not_manage(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $otherTenant = Tenant::factory()->create(['status' => 'active']);
        $adminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $outsider = User::factory()->withTenantRole($otherTenant, 'client', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$outsider->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_reset_password_of_another_admin_tenant(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $adminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $peerAdminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$peerAdminTenant->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_reset_password_of_root(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $adminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);
        $root = User::factory()->root()->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$root->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_tenant_cannot_reset_own_password_via_admin_endpoint(): void
    {
        // Decisión: sin excepción de self-reset. La cuenta propia se
        // administra desde /profile -> POST /password/change (requiere la
        // contraseña actual); este endpoint no tiene un caso de uso
        // legítimo sobre uno mismo.
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $adminTenant = User::factory()->withTenantRole($tenant, 'admin_tenant', true)
            ->create(['status' => 'active']);

        $response = $this->actingAs($adminTenant)->postJson("/api/users/{$adminTenant->id}/reset-password", [
            'action' => 'force_change_only',
        ]);

        $response->assertStatus(403);
    }
}
