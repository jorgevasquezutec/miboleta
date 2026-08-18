<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;
use App\Support\TenantAccessCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Las empresas de un usuario se cachean una hora (TenantFilter y
 * TenantFilterScope). Antes solo se invalidaban al cambiar el ESTADO de una
 * empresa, así que asignar o quitar empresas a un usuario tardaba hasta 60
 * minutos en surtir efecto: la ficha decía una cosa y el backend respondía 403
 * "Las empresas seleccionadas no están asociadas a tu cuenta".
 *
 * Se usa `/api/roles` porque no depende de permisos ni del rol por empresa: lo
 * único que se está midiendo es la decisión del middleware de filtro.
 */
class TenantAccessCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_asignar_una_empresa_invalida_la_cache_del_usuario(): void
    {
        $ownTenant = Tenant::factory()->create(['status' => 'active']);
        $newTenant = Tenant::factory()->create(['status' => 'active']);

        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $user->tenants()->attach($ownTenant->id, ['is_primary' => true]);

        // Calienta la caché: a partir de aquí el middleware la reutiliza
        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $ownTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(200);

        $this->assertNotNull(Cache::get(TenantAccessCache::activeTenantIdsKey($user->id)));

        // Asignación de la nueva empresa por la vía de la aplicación
        app(TenantService::class)->addUserToTenant((string) $newTenant->id, (string) $user->id);

        // Sin invalidar, esta petición respondía 403 hasta que expirara la caché
        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $newTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(200);
    }

    public function test_retirar_una_empresa_invalida_la_cache_del_usuario(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $primaryTenant = Tenant::factory()->create(['status' => 'active']);
        $secondTenant = Tenant::factory()->create(['status' => 'active']);

        $root = User::factory()->root()->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $user->tenants()->attach($primaryTenant->id, ['is_primary' => true]);
        $user->tenants()->attach($secondTenant->id, ['is_primary' => false]);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $secondTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(200);

        app(TenantService::class)->removeUserFromTenant((string) $secondTenant->id, (string) $user->id, $root);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $secondTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(403);
    }

    public function test_editar_las_empresas_del_usuario_invalida_la_cache(): void
    {
        $oldTenant = Tenant::factory()->create(['status' => 'active']);
        $newTenant = Tenant::factory()->create(['status' => 'active']);

        $user = User::factory()->create(['status' => 'active', 'must_change_password' => false]);
        $user->tenants()->attach($oldTenant->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $oldTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(200);

        // Edición de empresas por la vía de la aplicación (UserService)
        app(\App\Services\UserService::class)->updateUser($user, [
            'tenant_ids' => [$newTenant->id],
            'primary_tenant_id' => $newTenant->id,
        ]);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $newTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(200);

        $this->actingAs($user)
            ->withHeader('X-Tenant-Ids', (string) $oldTenant->id)
            ->getJson('/api/roles')
            ->assertStatus(403);
    }

    public function test_forget_borra_las_dos_claves(): void
    {
        Cache::put(TenantAccessCache::tenantIdsKey(7), [1], 60);
        Cache::put(TenantAccessCache::activeTenantIdsKey(7), [1], 60);

        TenantAccessCache::forget(7);

        $this->assertNull(Cache::get(TenantAccessCache::tenantIdsKey(7)));
        $this->assertNull(Cache::get(TenantAccessCache::activeTenantIdsKey(7)));
    }
}
