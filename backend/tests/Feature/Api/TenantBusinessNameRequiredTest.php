<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantBusinessNameRequiredTest extends TestCase
{
    use RefreshDatabase;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->root = User::factory()->root()->create(['status' => 'active']);
    }

    public function test_crear_sin_razon_social_devuelve_422(): void
    {
        $r = $this->actingAs($this->root)->postJson('/api/tenants', [
            'name' => 'Empresa Sin Razon',
            'ruc' => '20111111111',
        ]);

        $r->assertStatus(422)->assertJsonValidationErrors(['business_name']);
        $this->assertSame('La razón social es obligatoria.', $r->json('errors.business_name.0'));
        $this->assertDatabaseMissing('tenants', ['ruc' => '20111111111']);
    }

    public function test_crear_con_razon_social_devuelve_201(): void
    {
        $r = $this->actingAs($this->root)->postJson('/api/tenants', [
            'name' => 'Empresa Con Razon',
            'ruc' => '20222222222',
            'business_name' => 'Empresa Con Razon S.A.C.',
        ]);

        $r->assertStatus(201);
        $this->assertDatabaseHas('tenants', ['ruc' => '20222222222', 'business_name' => 'Empresa Con Razon S.A.C.']);
    }

    public function test_actualizar_con_razon_social_vacia_devuelve_422(): void
    {
        $tenant = Tenant::factory()->create();

        foreach (['', null] as $value) {
            $this->actingAs($this->root)
                ->putJson("/api/tenants/{$tenant->id}", ['business_name' => $value])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['business_name']);
        }
    }

    public function test_actualizar_sin_enviar_razon_social_no_la_toca(): void
    {
        $tenant = Tenant::factory()->create(['business_name' => 'Original S.A.C.']);

        $this->actingAs($this->root)
            ->putJson("/api/tenants/{$tenant->id}", ['name' => 'Nuevo nombre'])
            ->assertOk();

        $this->assertSame('Original S.A.C.', $tenant->fresh()->business_name);
    }
}
