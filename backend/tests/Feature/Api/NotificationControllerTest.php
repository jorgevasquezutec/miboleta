<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->client()->create(['status' => 'active']);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_user_can_list_notifications(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'title', 'message', 'read_at'],
                ],
                'meta',
            ]);

        $this->assertEquals(5, $response->json('meta.total'));
    }

    public function test_user_sees_only_own_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $otherUser = User::factory()->create();
        Notification::factory()->count(2)->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_user_can_filter_unread_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications?unread_only=true');

        $this->assertEquals(3, $response->json('meta.total'));
    }

    public function test_user_can_get_unread_count(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonFragment(['count' => 5]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        // Route uses PUT, not PATCH
        $response = $this->actingAs($this->user)->putJson("/api/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_as_read(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        // Correct route is /api/notifications/read-all with PUT
        $response = $this->actingAs($this->user)->putJson('/api/notifications/read-all');

        $response->assertStatus(200);

        $unreadCount = Notification::forUser($this->user->id)->unread()->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_can_delete_notification(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/notifications/{$notification->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_read_other_users_notification(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Route uses PUT, not PATCH
        $response = $this->actingAs($this->user)->putJson("/api/notifications/{$notification->id}/read");

        // API returns 404 when notification not found (filtered by user)
        $response->assertStatus(404);
    }

    public function test_user_cannot_delete_other_users_notification(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/notifications/{$notification->id}");

        // API returns 404 when notification not found (filtered by user)
        $response->assertStatus(404);
    }

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }
}
