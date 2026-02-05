<?php

namespace Tests\Unit\Services;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $notificationService;
    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        Event::fake();

        $this->notificationService = new NotificationService();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);

        $this->user = User::factory()->client()->create(['status' => 'active']);
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_create_notification_returns_notification(): void
    {
        $notification = $this->notificationService->create(
            userId: $this->user->id,
            type: 'test_type',
            title: 'Test Title',
            message: 'Test message',
            data: ['key' => 'value'],
            actionUrl: '/test',
            tenantId: $this->tenant->id
        );

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals('test_type', $notification->type);
        $this->assertEquals('Test Title', $notification->title);
        $this->assertEquals('Test message', $notification->message);
        $this->assertEquals('/test', $notification->action_url);
    }

    public function test_get_notifications_for_user(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $otherUser = User::factory()->create();
        Notification::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $notifications = $this->notificationService->getForUser($this->user);

        $this->assertEquals(3, $notifications->total());
    }

    public function test_get_unread_only(): void
    {
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now(),
        ]);

        $notifications = $this->notificationService->getForUser($this->user, ['unread_only' => true]);

        $this->assertEquals(2, $notifications->total());
    }

    public function test_get_unread_count(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now(),
        ]);

        $count = $this->notificationService->getUnreadCount($this->user);

        $this->assertEquals(3, $count);
    }

    public function test_mark_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        $this->assertNull($notification->read_at);

        $updated = $this->notificationService->markAsRead($notification);

        $this->assertNotNull($updated->read_at);
    }

    public function test_mark_all_as_read(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        $count = $this->notificationService->markAllAsRead($this->user);

        $this->assertEquals(5, $count);
        $this->assertEquals(0, $this->notificationService->getUnreadCount($this->user));
    }

    public function test_delete_notification(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->notificationService->delete($notification);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_notify_new_document(): void
    {
        $notification = $this->notificationService->notifyNewDocument(
            userId: $this->user->id,
            documentId: 1,
            documentName: 'Boleta Enero',
            tenantId: $this->tenant->id
        );

        $this->assertEquals(Notification::TYPE_DOCUMENT_NEW, $notification->type);
        $this->assertStringContainsString('Boleta Enero', $notification->message);
        $this->assertEquals(['document_id' => 1], $notification->data);
    }

    public function test_notify_document_signed(): void
    {
        $notification = $this->notificationService->notifyDocumentSigned(
            adminUserId: $this->user->id,
            documentId: 1,
            documentName: 'Contrato',
            signerName: 'Juan Perez',
            tenantId: $this->tenant->id
        );

        $this->assertEquals(Notification::TYPE_DOCUMENT_SIGNED, $notification->type);
        $this->assertStringContainsString('Juan Perez', $notification->message);
        $this->assertStringContainsString('Contrato', $notification->message);
    }

    public function test_notify_vacation_created(): void
    {
        $notification = $this->notificationService->notifyVacationCreated(
            supervisorId: $this->user->id,
            vacationId: 1,
            employeeName: 'Maria Garcia',
            dateRange: '01/02 - 05/02/2024',
            tenantId: $this->tenant->id
        );

        $this->assertEquals(Notification::TYPE_VACATION_CREATED, $notification->type);
        $this->assertStringContainsString('Maria Garcia', $notification->message);
    }

    public function test_notify_vacation_approved(): void
    {
        $notification = $this->notificationService->notifyVacationApproved(
            employeeId: $this->user->id,
            vacationId: 1,
            dateRange: '01/02 - 05/02/2024',
            approverName: 'Pedro Lopez',
            tenantId: $this->tenant->id
        );

        $this->assertEquals(Notification::TYPE_VACATION_APPROVED, $notification->type);
        $this->assertStringContainsString('Pedro Lopez', $notification->message);
    }

    public function test_notify_vacation_rejected_with_reason(): void
    {
        $notification = $this->notificationService->notifyVacationRejected(
            employeeId: $this->user->id,
            vacationId: 1,
            dateRange: '01/02 - 05/02/2024',
            rejecterName: 'Pedro Lopez',
            reason: 'Necesitamos personal',
            tenantId: $this->tenant->id
        );

        $this->assertEquals(Notification::TYPE_VACATION_REJECTED, $notification->type);
        $this->assertStringContainsString('Necesitamos personal', $notification->message);
    }

    public function test_filter_by_tenant_ids(): void
    {
        $tenant2 = Tenant::factory()->create();

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $tenant2->id,
        ]);

        $notifications = $this->notificationService->getForUser($this->user, [
            'tenant_ids' => [$this->tenant->id],
        ]);

        $this->assertEquals(2, $notifications->total());
    }
}
