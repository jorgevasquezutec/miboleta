<?php

namespace Database\Factories;

use App\Models\VacationRequest;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VacationRequest>
 */
class VacationRequestFactory extends Factory
{
    protected $model = VacationRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+1 week', '+2 months');
        $endDate = (clone $startDate)->modify('+' . fake()->numberBetween(1, 5) . ' days');
        $daysRequested = $startDate->diff($endDate)->days + 1;

        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_requested' => $daysRequested,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    /**
     * Set vacation as approved.
     */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }

    /**
     * Set vacation as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set vacation as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set vacation as taken.
     */
    public function taken(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'approved_at' => now()->subDays(7),
            'was_taken' => true,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Set vacation as not taken.
     */
    public function notTaken(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'approved_at' => now()->subDays(7),
            'was_taken' => false,
            'confirmed_at' => now(),
        ]);
    }
}
