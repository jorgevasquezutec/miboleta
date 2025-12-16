# TASK: Testing Backend con PHPUnit

**Fecha:** 2025-12-15
**Estado:** ✅ Tests Unitarios Completados - En Progreso
**Prioridad:** Alta
**Última actualización:** 2025-12-15 20:30

---

## ✅ Estado Actual

**Tests Totales:** 63 passed, 6 failed, 1 skipped (155 assertions)

### Tests Unitarios ✅
- ✅ **39 passed** - UserTest, TenantTest, DocumentTest, VacationRequestTest
- ✅ Factories completas (User, Tenant, Document, DocumentType, VacationRequest)

### Tests de Feature (API) 🟡
- ✅ **AuthenticationTest** - 7 passed, 1 skipped (8 tests)
- ✅ **DocumentsControllerTest** - 6 passed, 3 failed (9 tests)
- ✅ **VacationsControllerTest** - 10 passed, 3 failed (14 tests)

**Failures**: Relacionados con endpoints faltantes/diferentes en el backend:
- Vacations: cancel, approve, reject endpoints (405/404)
- Documents: algunos filtros necesitan ajustes

---

## Objetivo

Implementar tests unitarios y de integración para el backend Laravel usando PHPUnit.

---

## Fase 1: Configuración de PHPUnit

### 1.1 Verificar instalación

PHPUnit ya viene instalado con Laravel. Verificar:

```bash
cd backend
php artisan test
```

### 1.2 Configurar `phpunit.xml`

Verificar que existe y contiene:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>app</directory>
        </include>
    </source>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
    </php>
</phpunit>
```

### 1.3 Crear base de datos de testing

Para tests con SQLite en memoria, ya está configurado arriba.

---

## Fase 2: Tests Unitarios de Modelos

### 2.1 Tests de User Model (`tests/Unit/Models/UserTest.php`)

```php
<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_full_name_attribute(): void
    {
        $user = User::factory()->create([
            'name' => 'Juan',
            'last_name' => 'Pérez',
        ]);

        $this->assertEquals('Juan Pérez', $user->fullName);
    }

    public function test_user_can_belong_to_multiple_tenants(): void
    {
        $user = User::factory()->create();
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        $user->tenants()->attach([$tenant1->id, $tenant2->id]);

        $this->assertCount(2, $user->tenants);
    }

    public function test_user_has_primary_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->create();

        $user->tenants()->attach($tenant->id, ['is_primary' => true]);

        $this->assertEquals($tenant->id, $user->primaryTenant->id);
    }

    public function test_user_status_is_active_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertEquals('active', $user->status);
    }
}
```

### 2.2 Tests de Tenant Model (`tests/Unit/Models/TenantTest.php`)

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Document;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_has_users(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();

        $tenant->users()->attach($user->id);

        $this->assertCount(1, $tenant->users);
    }

    public function test_tenant_can_have_documents(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(1, $tenant->documents);
    }

    public function test_tenant_status_is_active_by_default(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertEquals('active', $tenant->status);
    }
}
```

### 2.3 Tests de Document Model (`tests/Unit/Models/DocumentTest.php`)

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Document;
use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $document = Document::factory()->create(['user_id' => $user->id]);

        $this->assertEquals($user->id, $document->user->id);
    }

    public function test_document_belongs_to_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $document->tenant->id);
    }

    public function test_document_status_is_pending_by_default(): void
    {
        $document = Document::factory()->create();

        $this->assertEquals('pending', $document->status);
    }

    public function test_document_can_be_signed(): void
    {
        $document = Document::factory()->create(['status' => 'pending']);
        
        $document->update([
            'status' => 'signed',
            'signed_at' => now(),
        ]);

        $this->assertEquals('signed', $document->status);
        $this->assertNotNull($document->signed_at);
    }
}
```

---

## Fase 3: Tests de Feature (API Endpoints)

### 3.1 Tests de Autenticación (`tests/Feature/Auth/AuthenticationTest.php`)

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'message']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }
}
```

### 3.2 Tests de Usuarios (`tests/Feature/Api/UsersControllerTest.php`)

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsersControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_admin_can_create_user(): void
    {
        $userData = [
            'name' => 'Nuevo',
            'last_name' => 'Usuario',
            'email' => 'nuevo@test.com',
            'password' => 'password123',
            'role' => 'client',
            'tenant_id' => $this->tenant->id,
        ];

        $response = $this->actingAs($this->admin)
            ->postJson('/api/users', $userData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'nuevo@test.com']);
    }

    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->putJson("/api/users/{$user->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200);
    }

    public function test_client_cannot_list_users(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($client)
            ->getJson('/api/users');

        $response->assertStatus(403);
    }
}
```

### 3.3 Tests de Documentos (`tests/Feature/Api/DocumentsControllerTest.php`)

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Document;
use App\Models\DocumentType;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocumentsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_user_can_list_their_documents(): void
    {
        Document::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/documents');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_their_document(): void
    {
        $document = Document::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $document->id]);
    }

    public function test_user_cannot_view_other_users_document(): void
    {
        $otherUser = User::factory()->create();
        $document = Document::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(403);
    }
}
```

### 3.4 Tests de Vacaciones (`tests/Feature/Api/VacationsControllerTest.php`)

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use App\Models\VacationRequest;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VacationsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, ['is_primary' => true]);
    }

    public function test_user_can_create_vacation_request(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/vacations', [
                'start_date' => now()->addDays(10)->format('Y-m-d'),
                'end_date' => now()->addDays(15)->format('Y-m-d'),
                'reason' => 'Vacaciones familiares',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vacation_requests', [
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
    }

    public function test_user_can_list_their_vacation_requests(): void
    {
        VacationRequest::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/vacations');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_cancel_pending_vacation(): void
    {
        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/vacations/{$vacation->id}/cancel");

        $response->assertStatus(200);
        $this->assertDatabaseHas('vacation_requests', [
            'id' => $vacation->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_admin_can_approve_vacation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/vacations/{$vacation->id}/approve");

        $response->assertStatus(200);
    }

    public function test_admin_can_reject_vacation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->tenants()->attach($this->tenant->id, ['is_primary' => true]);

        $vacation = VacationRequest::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/api/vacations/{$vacation->id}/reject", [
                'rejection_reason' => 'Fechas no disponibles',
            ]);

        $response->assertStatus(200);
    }
}
```

---

## Fase 4: Tests de Servicios

### 4.1 Tests de ReportsService (`tests/Unit/Services/ReportsServiceTest.php`)

```php
<?php

namespace Tests\Unit\Services;

use App\Services\ReportsService;
use App\Models\Document;
use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ReportsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportsService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportsService();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_get_document_stats_returns_correct_counts(): void
    {
        $user = User::factory()->create();

        // Crear documentos con diferentes estados
        Document::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        Document::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => 'signed',
        ]);

        $stats = $this->service->getDocumentStats($this->tenant->id);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['pending']);
        $this->assertEquals(2, $stats['signed']);
    }

    public function test_get_user_stats_returns_correct_counts(): void
    {
        User::factory()->count(3)->create(['status' => 'active'])
            ->each(fn($u) => $u->tenants()->attach($this->tenant->id));

        User::factory()->count(2)->create(['status' => 'inactive'])
            ->each(fn($u) => $u->tenants()->attach($this->tenant->id));

        $stats = $this->service->getUserStats($this->tenant->id);

        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(3, $stats['active']);
        $this->assertEquals(2, $stats['inactive']);
    }
}
```

---

## Fase 5: Factories

### 5.1 Crear/Verificar Factories necesarias

```bash
# Verificar factories existentes
ls backend/database/factories/
```

Factories necesarias:
- ✅ `UserFactory.php`
- ✅ `TenantFactory.php`
- ✅ `DocumentFactory.php`
- ✅ `VacationRequestFactory.php`
- ✅ `DocumentTypeFactory.php`

---

## Fase 6: Ejecutar Tests

### 6.1 Ejecutar todos los tests

```bash
cd backend
php artisan test
```

### 6.2 Ejecutar tests con coverage

```bash
php artisan test --coverage
```

### 6.3 Ejecutar tests específicos

```bash
php artisan test --filter=AuthenticationTest
php artisan test --filter=UserTest
php artisan test tests/Feature/Api/
```

### 6.4 Ejecutar en paralelo

```bash
php artisan test --parallel
```

---

## Criterios de Aceptación

- [x] PHPUnit configurado correctamente
- [x] Factories para todos los modelos principales
- [x] Tests unitarios para modelos (39 tests pasando)
- [x] Tests de feature para endpoints de API (Alta prioridad completados: Auth, Documents, Vacations)
- [ ] Tests para servicios principales
- [ ] Al menos 70% de cobertura en código crítico
- [ ] CI/CD ejecuta tests automáticamente

### Archivos Creados
- `backend/tests/Feature/Auth/AuthenticationTest.php` ✅
- `backend/tests/Feature/Api/DocumentsControllerTest.php` ✅
- `backend/tests/Feature/Api/VacationsControllerTest.php` ✅

---

## Prioridad de Tests

1. **Alta:** Autenticación (login, logout, permisos)
2. **Alta:** Documentos (CRUD, firma)
3. **Alta:** Vacaciones (solicitud, aprobación)
4. **Media:** Usuarios y Tenants
5. **Media:** Reportes y Auditoría
6. **Baja:** Notificaciones

---

## Notas

- Usar `RefreshDatabase` trait para limpiar DB entre tests
- Usar factories para crear datos de prueba
- Usar `actingAs()` para autenticar usuarios en tests
- Usar `assertDatabaseHas()` para verificar persistencia
- Configurar SQLite en memoria para tests rápidos

*Última actualización: 2025-12-15*
