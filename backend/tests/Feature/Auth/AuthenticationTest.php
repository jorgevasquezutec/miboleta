<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_user_can_login_with_document_number(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'document_text' => '87654321',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->postJson('/api/login', [
            'login' => '87654321',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->postJson('/api/login', [
            'login' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $tenant = Tenant::factory()->create();
        $user = User::factory()->client()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'status' => 'inactive',
        ]);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->postJson('/api/login', [
            'login' => 'inactive@example.com',
            'password' => 'password',
        ]);

        // Backend returns 422 with validation error for inactive users
        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tu cuenta se encuentra inactiva. Contacta al administrador.']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->actingAs($user)
            ->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['status' => 'active']);
        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->actingAs($user)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_login_requires_login_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_deleted_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'borrado@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $user->delete();

        // 422 y no 401: el login rechaza por ValidationException con el mismo
        // mensaje genérico que unas credenciales malas, para no revelar que la
        // cuenta existe pero está eliminada.
        $this->postJson('/api/login', [
            'login' => 'borrado@example.com',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_refresh_rejects_deleted_user_without_crashing(): void
    {
        // El soft delete NO borra la fila de refresh_tokens (su FK en cascada
        // es de BD y solo dispara con DELETE físico), pero la relación ->user
        // sí devuelve null por el scope global. Antes eso reventaba con
        // "property on null" y respondía 500 en bucle hasta que el token
        // expirara; ahora debe ser un rechazo limpio y dejar el token revocado.
        $user = User::factory()->create(['status' => 'active']);
        $refreshToken = \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token' => 'token-de-usuario-eliminado',
            'expires_at' => now()->addDays(30),
            'is_revoked' => false,
        ]);

        $user->delete();

        $result = app(\App\Services\AuthService::class)
            ->refreshAccessToken('token-de-usuario-eliminado');

        $this->assertNull($result);
        $this->assertDatabaseHas('refresh_tokens', [
            'id' => $refreshToken->id,
            'is_revoked' => true,
        ]);
    }
}
