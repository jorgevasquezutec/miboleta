<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El borrado de usuarios es lógico y los índices UNIQUE de `email` y
 * `document_text` no distinguen borrados: el registro eliminado sigue ocupando
 * esos datos, pero no aparece en ningún listado. El administrador veía "Este
 * email ya está registrado" señalando a un usuario invisible.
 *
 * Reutilizar los datos de un usuario eliminado sigue bloqueado (decisión del
 * cliente); lo que cambia es que el mensaje diga de quién son y desde cuándo.
 */
class DeletedUserIdentityCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function rootActor(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        return User::factory()->root()->create(['status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        return array_merge([
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@example.com',
            'document_type' => 'dni',
            'document_text' => '11112222',
            'tenant_id' => $tenant->id,
        ], $overrides);
    }

    public function test_crear_con_el_correo_de_un_usuario_eliminado_explica_el_motivo(): void
    {
        $root = $this->rootActor();

        $deleted = User::factory()->create([
            'name' => 'Shirley',
            'last_name' => 'Quispe',
            'email' => 'shirley@example.com',
            'document_text' => '27384756',
        ]);
        $deleted->delete();

        $response = $this->actingAs($root)
            ->postJson('/api/users', $this->payload(['email' => 'shirley@example.com']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $message = $response->json('errors.email.0');
        $this->assertStringContainsString('usuario eliminado', $message);
        $this->assertStringContainsString('Shirley Quispe', $message);
        $this->assertStringContainsString($deleted->deleted_at->format('d/m/Y'), $message);
        $this->assertNotSame('Este email ya está registrado', $message);
    }

    public function test_crear_con_el_documento_de_un_usuario_eliminado_explica_el_motivo(): void
    {
        $root = $this->rootActor();

        $deleted = User::factory()->create([
            'name' => 'Shirley',
            'last_name' => 'Quispe',
            'email' => 'shirley2@example.com',
            'document_text' => '27384756',
        ]);
        $deleted->delete();

        $response = $this->actingAs($root)
            ->postJson('/api/users', $this->payload(['document_text' => '27384756']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document_text']);

        $message = $response->json('errors.document_text.0');
        $this->assertStringContainsString('usuario eliminado', $message);
        $this->assertStringContainsString('Shirley Quispe', $message);
    }

    public function test_colision_con_un_usuario_activo_conserva_el_mensaje_de_siempre(): void
    {
        $root = $this->rootActor();

        User::factory()->create([
            'email' => 'activo@example.com',
            'document_text' => '33334444',
        ]);

        $response = $this->actingAs($root)
            ->postJson('/api/users', $this->payload(['email' => 'activo@example.com']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame('Este email ya está registrado', $response->json('errors.email.0'));
    }

    public function test_editar_hacia_el_correo_de_un_usuario_eliminado_explica_el_motivo(): void
    {
        $root = $this->rootActor();
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $deleted = User::factory()->create([
            'name' => 'Shirley',
            'last_name' => 'Quispe',
            'email' => 'shirley3@example.com',
        ]);
        $deleted->delete();

        $target = User::factory()->create(['status' => 'active']);
        $target->tenants()->attach($tenant->id, ['is_primary' => true]);

        $response = $this->actingAs($root)
            ->putJson("/api/users/{$target->id}", ['email' => 'shirley3@example.com']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertStringContainsString('usuario eliminado', $response->json('errors.email.0'));
    }

    public function test_editar_conservando_el_propio_correo_no_falla(): void
    {
        $root = $this->rootActor();
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $target = User::factory()->create(['status' => 'active', 'email' => 'propio@example.com']);
        $target->tenants()->attach($tenant->id, ['is_primary' => true]);

        $this->actingAs($root)
            ->putJson("/api/users/{$target->id}", [
                'name' => 'Nombre nuevo',
                'email' => 'propio@example.com',
            ])
            ->assertStatus(200);
    }
}
