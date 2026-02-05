<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Storage::fake('public');

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->client()->create([
            'name' => 'Juan',
            'last_name' => 'Perez',
            'phone' => '999888777',
            'status' => 'active',
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_user_can_get_profile(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'name',
                'last_name',
                'full_name',
                'email',
                'phone',
                'role',
                'tenants',
            ]);
    }

    public function test_profile_returns_correct_user_data(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Juan',
                'last_name' => 'Perez',
                'phone' => '999888777',
            ]);
    }

    public function test_user_can_update_name(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Juan Carlos',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Juan Carlos']);

        $this->assertEquals('Juan Carlos', $this->user->fresh()->name);
    }

    public function test_user_can_update_phone(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'phone' => '111222333',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['phone' => '111222333']);
    }

    public function test_user_can_update_multiple_fields(): void
    {
        $response = $this->actingAs($this->user)->putJson('/api/profile', [
            'name' => 'Pedro',
            'last_name' => 'Gomez',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Pedro',
                'last_name' => 'Gomez',
            ]);
    }

    public function test_user_can_upload_avatar(): void
    {
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $response = $this->actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['avatar_url']);

        $this->assertNotNull($this->user->fresh()->avatar_url);
    }

    public function test_upload_avatar_validates_file_type(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_can_delete_avatar(): void
    {
        // First upload an avatar
        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->actingAs($this->user)->postJson('/api/profile/avatar', [
            'avatar' => $file,
        ]);

        $this->assertNotNull($this->user->fresh()->avatar_url);

        // Delete avatar
        $response = $this->actingAs($this->user)->deleteJson('/api/profile/avatar');

        $response->assertStatus(200);
        $this->assertNull($this->user->fresh()->avatar_url);
    }

    public function test_unauthenticated_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_cannot_update_profile(): void
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'Hacker',
        ]);

        $response->assertStatus(401);
    }
}
