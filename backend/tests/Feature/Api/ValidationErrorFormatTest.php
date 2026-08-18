<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrato de un 422 de validación en la API: siempre `{message, errors}`,
 * con TODOS los campos que fallaron en `errors` y sin el sufijo en inglés
 * "(and N more errors)" que añade `ValidationException::summarize()` en
 * `message`.
 *
 * `POST /api/tenants` es el caso relevante porque antes pasaba por
 * `CustomFormRequest::failedValidation()` (`StoreTenantRequest`), que se
 * quedaba solo con el primer mensaje del primer campo y no devolvía
 * `errors` en absoluto. Ver openspec/changes/centralizar-manejo-de-errores-api.
 */
class ValidationErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_empresa_con_varios_campos_invalidos_devuelve_todos_los_errores(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $root = User::factory()->root()->create(['status' => 'active']);

        // 'name' falta (required) y 'ruc' no mide 11 caracteres (size): dos
        // campos inválidos a la vez.
        $response = $this->actingAs($root)->postJson('/api/tenants', [
            'ruc' => '123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'ruc']);

        $errors = $response->json('errors');
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('ruc', $errors);
    }

    public function test_mensaje_de_resumen_de_un_422_no_trae_el_sufijo_en_ingles(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $root = User::factory()->root()->create(['status' => 'active']);

        $response = $this->actingAs($root)->postJson('/api/tenants', [
            'ruc' => '123',
        ]);

        $response->assertStatus(422);

        $message = $response->json('message');
        $this->assertNotEmpty($message);
        $this->assertStringNotContainsString('(and', $message);
    }
}
