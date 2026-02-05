<?php

namespace Tests\Unit\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfileService $profileService;
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Storage::fake('public');

        $this->profileService = new ProfileService();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->client()->create([
            'name' => 'Juan',
            'last_name' => 'Perez',
            'phone' => '999888777',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_get_profile_returns_correct_structure(): void
    {
        $profile = $this->profileService->getProfile($this->user);

        $this->assertArrayHasKey('id', $profile);
        $this->assertArrayHasKey('name', $profile);
        $this->assertArrayHasKey('last_name', $profile);
        $this->assertArrayHasKey('full_name', $profile);
        $this->assertArrayHasKey('email', $profile);
        $this->assertArrayHasKey('role', $profile);
        $this->assertArrayHasKey('roles', $profile);
        $this->assertArrayHasKey('tenants', $profile);
    }

    public function test_get_profile_returns_correct_values(): void
    {
        $profile = $this->profileService->getProfile($this->user);

        $this->assertEquals('Juan', $profile['name']);
        $this->assertEquals('Perez', $profile['last_name']);
        $this->assertEquals('Juan Perez', $profile['full_name']);
        $this->assertEquals('999888777', $profile['phone']);
    }

    public function test_get_profile_includes_tenants(): void
    {
        $profile = $this->profileService->getProfile($this->user);

        $this->assertCount(1, $profile['tenants']);
        $this->assertEquals($this->tenant->id, $profile['tenants'][0]['id']);
        // is_primary may be 1 or true depending on database driver
        $this->assertEquals(1, (int) $profile['tenants'][0]['is_primary']);
    }

    public function test_update_profile_changes_name(): void
    {
        $updated = $this->profileService->updateProfile($this->user, [
            'name' => 'Juan Carlos',
        ]);

        $this->assertEquals('Juan Carlos', $updated->name);
    }

    public function test_update_profile_changes_phone(): void
    {
        $updated = $this->profileService->updateProfile($this->user, [
            'phone' => '111222333',
        ]);

        $this->assertEquals('111222333', $updated->phone);
    }

    public function test_update_profile_multiple_fields(): void
    {
        $updated = $this->profileService->updateProfile($this->user, [
            'name' => 'Pedro',
            'last_name' => 'Gomez',
            'phone' => '555666777',
        ]);

        $this->assertEquals('Pedro', $updated->name);
        $this->assertEquals('Gomez', $updated->last_name);
        $this->assertEquals('555666777', $updated->phone);
    }

    public function test_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $avatarUrl = $this->profileService->uploadAvatar($this->user, $file);

        $this->assertNotEmpty($avatarUrl);
        $this->assertStringContainsString('avatar_', $this->user->fresh()->avatar_url);
        Storage::disk('public')->assertExists($this->user->fresh()->getRawOriginal('avatar_url'));
    }

    public function test_upload_avatar_replaces_old_avatar(): void
    {
        // Upload first avatar with jpg extension
        $file1 = UploadedFile::fake()->image('avatar1.jpg', 200, 200);
        $this->profileService->uploadAvatar($this->user, $file1);
        $oldPath = $this->user->fresh()->getRawOriginal('avatar_url');
        Storage::disk('public')->assertExists($oldPath);

        // Upload second avatar with png extension to ensure different filename
        $file2 = UploadedFile::fake()->image('avatar2.png', 200, 200);
        $this->profileService->uploadAvatar($this->user, $file2);
        $newPath = $this->user->fresh()->getRawOriginal('avatar_url');

        // Files should have different extensions at minimum
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_delete_avatar(): void
    {
        // First upload an avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->profileService->uploadAvatar($this->user, $file);

        // Refresh the user to get updated attributes
        $this->user->refresh();
        $path = $this->user->getRawOriginal('avatar_url');

        Storage::disk('public')->assertExists($path);

        // Delete the avatar (using refreshed user)
        $result = $this->profileService->deleteAvatar($this->user);

        $this->assertTrue($result);
        $this->assertNull($this->user->fresh()->getRawOriginal('avatar_url'));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_avatar_when_none_exists(): void
    {
        $result = $this->profileService->deleteAvatar($this->user);

        $this->assertFalse($result);
    }
}
