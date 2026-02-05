<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'type' => fake()->randomElement([
                Notification::TYPE_DOCUMENT_NEW,
                Notification::TYPE_DOCUMENT_SIGNED,
                Notification::TYPE_VACATION_CREATED,
                Notification::TYPE_VACATION_APPROVED,
                Notification::TYPE_VACATION_REJECTED,
            ]),
            'title' => fake()->sentence(3),
            'message' => fake()->sentence(8),
            'data' => null,
            'action_url' => '/dashboard',
            'read_at' => null,
        ];
    }

    /**
     * Mark notification as read.
     */
    public function read(): static
    {
        return $this->state(fn(array $attributes) => [
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread.
     */
    public function unread(): static
    {
        return $this->state(fn(array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Set notification type to document new.
     */
    public function documentNew(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Notification::TYPE_DOCUMENT_NEW,
            'title' => 'Nuevo documento disponible',
        ]);
    }

    /**
     * Set notification type to vacation created.
     */
    public function vacationCreated(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => Notification::TYPE_VACATION_CREATED,
            'title' => 'Nueva solicitud de vacaciones',
        ]);
    }
}
