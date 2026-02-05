<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\PasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordService $passwordService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->passwordService = new PasswordService();

        $this->user = User::factory()->client()->create([
            'password' => Hash::make('oldpassword'),
            'must_change_password' => false,
        ]);
    }

    public function test_generate_temporary_password_has_correct_length(): void
    {
        $password = $this->passwordService->generateTemporaryPassword();

        $this->assertEquals(12, strlen($password));
    }

    public function test_request_password_reset_for_existing_user(): void
    {
        $result = $this->passwordService->requestPasswordReset($this->user->email);

        $this->assertTrue($result);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $this->user->email,
        ]);

        Mail::assertQueued(\App\Mail\ForgotPasswordMail::class);
    }

    public function test_request_password_reset_for_nonexistent_user(): void
    {
        $result = $this->passwordService->requestPasswordReset('nonexistent@example.com');

        $this->assertFalse($result);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $token = 'test-token-123456';

        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $result = $this->passwordService->resetPasswordWithToken(
            $this->user->email,
            $token,
            'newpassword123'
        );

        $this->assertTrue($result['success']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
        $this->assertFalse($this->user->must_change_password);
    }

    public function test_reset_password_with_invalid_token(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make('correct-token'),
            'created_at' => now(),
        ]);

        $result = $this->passwordService->resetPasswordWithToken(
            $this->user->email,
            'wrong-token',
            'newpassword123'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Token inválido', $result['message']);
    }

    public function test_reset_password_with_expired_token(): void
    {
        $token = 'expired-token';

        // Insert token with current time
        DB::table('password_reset_tokens')->insert([
            'email' => $this->user->email,
            'token' => Hash::make($token),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        // Travel 120 minutes into the future to make token expired (>60 min)
        $this->travel(120)->minutes();

        $result = $this->passwordService->resetPasswordWithToken(
            $this->user->email,
            $token,
            'newpassword123'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expirado', $result['message']);
    }

    public function test_change_password_with_correct_current_password(): void
    {
        $result = $this->passwordService->changePassword(
            $this->user,
            'oldpassword',
            'newpassword123'
        );

        $this->assertTrue($result['success']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_change_password_with_incorrect_current_password(): void
    {
        $result = $this->passwordService->changePassword(
            $this->user,
            'wrongpassword',
            'newpassword123'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('incorrecta', $result['message']);
    }

    public function test_force_change_password_when_required(): void
    {
        $this->user->update(['must_change_password' => true]);

        $result = $this->passwordService->forceChangePassword(
            $this->user,
            'newpassword123'
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['user']->must_change_password);
        $this->assertTrue(Hash::check('newpassword123', $result['user']->password));
    }

    public function test_force_change_password_when_not_required(): void
    {
        $this->user->update(['must_change_password' => false]);

        $result = $this->passwordService->forceChangePassword(
            $this->user,
            'newpassword123'
        );

        $this->assertFalse($result['success']);
        $this->assertNull($result['user']);
    }

    public function test_admin_reset_password_generate(): void
    {
        $result = $this->passwordService->adminResetPassword(
            $this->user,
            'generate',
            null,
            true
        );

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['password']);
        $this->assertEquals(12, strlen($result['password']));
        $this->assertTrue($result['email_sent']);

        $this->user->refresh();
        $this->assertTrue($this->user->must_change_password);
        $this->assertTrue(Hash::check($result['password'], $this->user->password));
    }

    public function test_admin_reset_password_manual(): void
    {
        $result = $this->passwordService->adminResetPassword(
            $this->user,
            'manual',
            'manualpassword123',
            false
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('manualpassword123', $result['password']);

        $this->user->refresh();
        $this->assertFalse($this->user->must_change_password);
        $this->assertTrue(Hash::check('manualpassword123', $this->user->password));
    }

    public function test_admin_reset_force_change_only(): void
    {
        $this->user->update(['must_change_password' => false]);

        $result = $this->passwordService->adminResetPassword(
            $this->user,
            'force_change_only'
        );

        $this->assertTrue($result['success']);
        $this->assertNull($result['password']);
        $this->assertFalse($result['email_sent']);

        $this->user->refresh();
        $this->assertTrue($this->user->must_change_password);
    }
}
