<?php

namespace Tests\Unit\Services;

use App\Mail\EmailChangedNotificationMail;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Tests for Issue 3: Email change notification.
 * When admin changes a user's email, both old and new email should receive notifications,
 * and user should be forced to change password.
 */
class EmailChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private UserService $userService;
    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Mail::fake();

        $this->userService = new UserService(new AuditService());

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->admin = User::factory()->admin()->create(['status' => 'active']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_email_change_sends_notification_to_both_emails(): void
    {
        $user = User::factory()->client()->create([
            'email' => 'old@example.com',
            'must_change_password' => false,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->actingAs($this->admin);

        $this->userService->updateUser($user, [
            'email' => 'new@example.com',
        ]);

        // Should queue notification to old email (Mailable implements ShouldQueue)
        Mail::assertQueued(EmailChangedNotificationMail::class, function ($mail) {
            return $mail->hasTo('old@example.com');
        });

        // Should queue notification to new email
        Mail::assertQueued(EmailChangedNotificationMail::class, function ($mail) {
            return $mail->hasTo('new@example.com');
        });
    }

    public function test_email_change_forces_password_change(): void
    {
        $user = User::factory()->client()->create([
            'email' => 'old@example.com',
            'must_change_password' => false,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->actingAs($this->admin);

        $updatedUser = $this->userService->updateUser($user, [
            'email' => 'new@example.com',
        ]);

        $updatedUser->refresh();
        $this->assertTrue($updatedUser->must_change_password);
    }

    public function test_no_notification_when_email_unchanged(): void
    {
        $user = User::factory()->client()->create([
            'email' => 'same@example.com',
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->actingAs($this->admin);

        $this->userService->updateUser($user, [
            'name' => 'New Name',
        ]);

        Mail::assertNotQueued(EmailChangedNotificationMail::class);
    }

    public function test_no_notification_when_same_email_provided(): void
    {
        $user = User::factory()->client()->create([
            'email' => 'same@example.com',
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $this->actingAs($this->admin);

        $this->userService->updateUser($user, [
            'email' => 'same@example.com',
        ]);

        Mail::assertNotQueued(EmailChangedNotificationMail::class);
    }

    public function test_email_changed_mailable_has_correct_data(): void
    {
        $user = User::factory()->create(['name' => 'Juan', 'last_name' => 'Perez']);
        $mailable = new EmailChangedNotificationMail($user, 'old@example.com', 'new@example.com');

        $this->assertEquals('old@example.com', $mailable->oldEmail);
        $this->assertEquals('new@example.com', $mailable->newEmail);
        $this->assertEquals($user->id, $mailable->user->id);
    }

    public function test_email_changed_mailable_has_correct_subject(): void
    {
        $user = User::factory()->create();
        $mailable = new EmailChangedNotificationMail($user, 'old@example.com', 'new@example.com');

        $envelope = $mailable->envelope();
        $this->assertStringContains('Cambio de correo electrónico', $envelope->subject);
    }

    /**
     * Custom assertion helper
     */
    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }
}
