<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'ruc' => fake()->numerify('###########'),
            'business_name' => fake()->company() . ' S.A.C.',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'logo_path' => null,
            'status' => 'active',
        ];
    }

    /**
     * Set tenant as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}
