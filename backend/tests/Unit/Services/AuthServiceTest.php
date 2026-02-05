<?php

namespace Tests\Unit\Services;

use App\Models\RefreshToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->authService = new AuthService();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->client()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_successful_login_returns_user_and_tokens(): void
    {
        $result = $this->authService->attemptLogin('test@example.com', 'password123');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertEquals($this->user->id, $result['user']->id);
        $this->assertInstanceOf(RefreshToken::class, $result['refresh_token']);
    }

    public function test_login_fails_with_invalid_email(): void
    {
        $result = $this->authService->attemptLogin('nonexistent@example.com', 'password123');

        $this->assertNull($result);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $result = $this->authService->attemptLogin('test@example.com', 'wrongpassword');

        $this->assertNull($result);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->user->update(['status' => 'inactive']);

        $result = $this->authService->attemptLogin('test@example.com', 'password123');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('user_inactive', $result['error']);
    }

    public function test_login_fails_when_all_tenants_are_inactive(): void
    {
        $this->tenant->update(['status' => 'inactive']);

        $result = $this->authService->attemptLogin('test@example.com', 'password123');

        $this->assertNotNull($result);
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('tenant_inactive', $result['error']);
    }

    public function test_login_updates_last_login_timestamp(): void
    {
        $this->assertNull($this->user->last_login_at);

        $this->authService->attemptLogin('test@example.com', 'password123');

        $this->user->refresh();
        $this->assertNotNull($this->user->last_login_at);
    }

    public function test_refresh_token_returns_new_access_token(): void
    {
        $loginResult = $this->authService->attemptLogin('test@example.com', 'password123');
        $refreshToken = $loginResult['refresh_token'];

        $result = $this->authService->refreshAccessToken($refreshToken->token);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertEquals($this->user->id, $result['user']->id);
    }

    public function test_refresh_token_fails_with_invalid_token(): void
    {
        $result = $this->authService->refreshAccessToken('invalid-token');

        $this->assertNull($result);
    }

    public function test_refresh_token_fails_when_revoked(): void
    {
        $loginResult = $this->authService->attemptLogin('test@example.com', 'password123');
        $refreshToken = $loginResult['refresh_token'];
        $refreshToken->revoke();

        $result = $this->authService->refreshAccessToken($refreshToken->token);

        $this->assertNull($result);
    }

    public function test_refresh_token_fails_for_inactive_user(): void
    {
        $loginResult = $this->authService->attemptLogin('test@example.com', 'password123');
        $refreshToken = $loginResult['refresh_token'];

        $this->user->update(['status' => 'inactive']);

        $result = $this->authService->refreshAccessToken($refreshToken->token);

        $this->assertNull($result);
    }

    public function test_logout_revokes_all_tokens(): void
    {
        $loginResult = $this->authService->attemptLogin('test@example.com', 'password123');
        $refreshToken = $loginResult['refresh_token'];

        $this->authService->logout($this->user);

        $this->assertCount(0, $this->user->fresh()->tokens);
        $this->assertTrue($refreshToken->fresh()->is_revoked);
    }

    public function test_transform_auth_user_returns_expected_structure(): void
    {
        $this->user->load(['roles', 'tenants']);

        $transformed = $this->authService->transformAuthUser($this->user);

        $this->assertArrayHasKey('id', $transformed);
        $this->assertArrayHasKey('name', $transformed);
        $this->assertArrayHasKey('email', $transformed);
        $this->assertArrayHasKey('role', $transformed);
        $this->assertArrayHasKey('roles', $transformed);
        $this->assertArrayHasKey('tenants', $transformed);
        $this->assertArrayHasKey('primary_tenant', $transformed);
        $this->assertEquals($this->user->email, $transformed['email']);
    }

    public function test_access_token_cookie_has_correct_attributes(): void
    {
        $cookie = $this->authService->createAccessTokenCookie('test-token');

        $this->assertEquals('access_token', $cookie->getName());
        $this->assertEquals('test-token', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_refresh_token_cookie_has_correct_attributes(): void
    {
        $cookie = $this->authService->createRefreshTokenCookie('test-token');

        $this->assertEquals('refresh_token', $cookie->getName());
        $this->assertEquals('test-token', $cookie->getValue());
        $this->assertTrue($cookie->isHttpOnly());
    }

    public function test_expired_cookie_has_negative_expiry(): void
    {
        $cookie = $this->authService->createExpiredCookie('access_token');

        $this->assertEquals('access_token', $cookie->getName());
        $this->assertEquals('', $cookie->getValue());
    }
}
