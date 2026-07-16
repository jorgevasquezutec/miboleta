<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Role;
use App\Models\UserTenantRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => fake()->numerify('########'),
            'phone' => fake()->phoneNumber(),
            'status' => 'active',
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Set user to require password change.
     */
    public function mustChangePassword(): static
    {
        return $this->state(fn(array $attributes) => [
            'must_change_password' => true,
        ]);
    }

    /**
     * Attach root role after creation.
     */
    public function root(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('name', 'root')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }

    /**
     * Attach admin role after creation.
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('name', 'admin')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }

    /**
     * Attach client role after creation.
     */
    public function client(): static
    {
        return $this->afterCreating(function (User $user) {
            $role = Role::where('name', 'client')->first();
            if ($role) {
                $user->roles()->attach($role->id);
            }
        });
    }

    /**
     * Asigna al usuario un rol operativo DENTRO de una empresa concreta
     * (user_tenant_roles), que es la fuente de verdad para autorizar, y lo
     * asocia a esa empresa (user_tenants).
     *
     * Es lo que hay que usar para tests de autorización: los states root()/
     * admin()/client() de arriba solo escriben el rol GLOBAL (user_roles), que
     * es un respaldo de display y NO debe usarse para autorizar.
     *
     * Encadenable para usuarios multi-empresa con roles distintos:
     *   User::factory()->withTenantRole($a, 'admin')->withTenantRole($b, 'client')->create();
     *
     * @param  \App\Models\Tenant|int  $tenant
     */
    public function withTenantRole($tenant, string $roleName, bool $isPrimary = false): static
    {
        $tenantId = is_object($tenant) ? $tenant->id : $tenant;

        return $this->afterCreating(function (User $user) use ($tenantId, $roleName, $isPrimary) {
            if (!$user->tenants()->where('tenants.id', $tenantId)->exists()) {
                $user->tenants()->attach($tenantId, ['is_primary' => $isPrimary]);
            }

            $role = Role::where('name', $roleName)->first();

            if ($role) {
                UserTenantRole::firstOrCreate([
                    'user_id' => $user->id,
                    'tenant_id' => $tenantId,
                    'role_id' => $role->id,
                ]);
            }
        });
    }
}
