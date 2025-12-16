<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'doc_type_id' => DocumentType::factory(),
            'period' => now()->format('Y-m'),
            'file_path' => 'documents/' . fake()->uuid() . '.pdf',
            'file_size' => fake()->numberBetween(10000, 500000),
            'original_name' => fake()->word() . '.pdf',
            'status' => 'pending',
            'requires_signature' => true,
            'notified' => false,
            'version' => 1,
        ];
    }

    /**
     * Set document as signed.
     */
    public function signed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'signed',
            'signed_at' => now(),
            'signature' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Set document as orphan.
     */
    public function orphan(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'orphan',
            'user_id' => null,
        ]);
    }

    /**
     * Set document as active.
     */
    public function active(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Set document as expired.
     */
    public function expired(): static
    {
        return $this->state(fn(array $attributes) => [
            'expires_at' => now()->subDays(1),
            'status' => 'expired',
        ]);
    }

    /**
     * Set document without signature requirement.
     */
    public function noSignature(): static
    {
        return $this->state(fn(array $attributes) => [
            'requires_signature' => false,
        ]);
    }
}
