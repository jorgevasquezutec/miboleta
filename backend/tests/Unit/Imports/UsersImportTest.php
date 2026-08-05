<?php

namespace Tests\Unit\Imports;

use App\Imports\UsersImport;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * [OBS-CLIENTE 2026-07]: el cliente pidió retirar el rol 'admin_tenant' de la
 * carga masiva de usuarios (columna "Rol en empresa {N}" / org{n}_rol) para
 * TODOS los importadores. [OBS-CLIENTE 2026-08] aclaró que root SÍ debe poder
 * asignarlo por esta vía; admin_tenant/admin siguen sin poder (ver
 * BulkUserUploadService::allowedOrgRolesFor).
 *
 * Se llama a UsersImport::collection() directamente (sin generar un .xlsx
 * real) porque WithHeadingRow ya deja las filas como colecciones asociativas
 * cuando Excel::import() las entrega; construir esas colecciones a mano con
 * claves canónicas (BulkUserColumns::canonicalKey es identidad para ellas)
 * ejercita el mismo parseRow()/parseOrganizations() sin el costo de un
 * archivo real.
 */
class UsersImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_row_with_admin_tenant_role_is_rejected_with_row_error(): void
    {
        // Sin actingAs(): Auth::user() es null dentro de parseOrganizations,
        // que allowedOrgRolesFor() trata como NO-root (fail-closed) -mismo
        // resultado que un importador admin_tenant no-root.
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '12345678',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'admin_tenant',
            ]),
        ]);

        $import->collection($rows);

        $errors = $import->getErrors();
        $this->assertNotEmpty($errors, 'La fila con admin_tenant debería producir un error');

        $roleError = collect($errors)->first(fn($error) => $error['field'] === 'org1_rol');

        $this->assertNotNull($roleError, 'Debe reportarse un error de fila en el campo org1_rol');
        $this->assertStringContainsString('admin_tenant', $roleError['message']);
        $this->assertStringContainsString('inválido', $roleError['message']);
        // Mensaje accionable: debe listar los roles sí permitidos, como
        // display names (Obs 2), no como slugs crudos.
        $this->assertStringContainsString('Admin Empleados, Empleado, Aprobador Empleado', $roleError['message']);

        // La fila no queda parseada como válida.
        $this->assertEmpty($import->getParsedData());
    }

    public function test_row_with_allowed_role_is_accepted(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '12345678',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'aprobador',
            ]),
        ]);

        $import->collection($rows);

        $this->assertEmpty($import->getErrors());
        $this->assertCount(1, $import->getParsedData());
    }

    /**
     * [OBS-CLIENTE 2026-08]: root sí puede asignar admin_tenant por carga
     * masiva. actingAs() fija el guard de auth para el proceso de test
     * completo (no solo peticiones HTTP), así que Auth::user() dentro de
     * parseOrganizations() resuelve a este root aunque se llame a
     * collection() directamente, sin pasar por un controller.
     */
    public function test_row_with_admin_tenant_role_is_accepted_when_importer_is_root(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $tenant = Tenant::factory()->create(['status' => 'active']);
        $root = User::factory()->root()->create(['status' => 'active']);
        $this->actingAs($root);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Root',
                'apellido' => 'Importer',
                'email' => 'root.importer@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '87654321',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'admin_tenant',
            ]),
        ]);

        $import->collection($rows);

        $this->assertEmpty($import->getErrors());
        $this->assertCount(1, $import->getParsedData());
        $this->assertSame(['admin_tenant'], $import->getParsedData()[0]['organizaciones'][0]['roles']);
    }

    /**
     * Obs 4: la columna "Número de documento" queda en formato General y
     * Excel numeriza un DNI tipeado como "01234567" a int 1234567 (el cero
     * inicial se pierde antes de que el import lo vea). normalizeRow()
     * repone el padding vía App\Support\DocumentNumber ANTES de la
     * validación de "requerido".
     */
    public function test_dni_arriving_as_int_is_padded_and_accepted(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => 1234567, // int, sin el cero inicial
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'client',
            ]),
        ]);

        $import->collection($rows);

        $this->assertEmpty($import->getErrors());
        $this->assertCount(1, $import->getParsedData());
        $this->assertSame('01234567', $import->getParsedData()[0]['numero_documento']);
    }

    /**
     * Obs 4: un DNI de puros ceros ("00000000") llega desde Excel como el
     * entero 0 (misma numerización de la celda que en el test anterior), que
     * antes de este fix hacía que empty($row['numero_documento']) fuera true
     * y la fila se rechazara como "requerido" en vez de aceptarse con su
     * valor real, ya con el padding repuesto.
     */
    public function test_dni_of_all_zeros_is_not_treated_as_missing(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => 0, // int: misma numerización que produce Excel
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'client',
            ]),
        ]);

        $import->collection($rows);

        $missingDocError = collect($import->getErrors())
            ->first(fn($error) => $error['field'] === 'numero_documento');

        $this->assertNull($missingDocError, 'El DNI "00000000" no debe reportarse como requerido/faltante');
        $this->assertCount(1, $import->getParsedData());
        $this->assertSame('00000000', $import->getParsedData()[0]['numero_documento']);
    }

    /**
     * Obs 2: la plantilla ahora muestra el display name ("Empleado") en el
     * dropdown de org{n}_rol; el import debe seguir aceptando el slug crudo
     * ("client") de archivos con el formato anterior.
     */
    public function test_org_role_slug_is_still_accepted(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '12345678',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'client',
            ]),
        ]);

        $import->collection($rows);

        $this->assertEmpty($import->getErrors());
        $this->assertCount(1, $import->getParsedData());
        $this->assertSame(['client'], $import->getParsedData()[0]['organizaciones'][0]['roles']);
    }

    /**
     * Obs 2: el display name ("Empleado") debe resolverse al mismo slug
     * ('client') que persiste en user_tenant_roles.
     */
    public function test_org_role_display_name_resolves_to_its_slug(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '12345678',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'Empleado',
            ]),
        ]);

        $import->collection($rows);

        $this->assertEmpty($import->getErrors());
        $this->assertCount(1, $import->getParsedData());
        $this->assertSame(['client'], $import->getParsedData()[0]['organizaciones'][0]['roles']);
    }

    /**
     * Obs 2: un rol inválido debe seguir rechazándose de fila, y el mensaje
     * debe listar los roles permitidos como display names (lo que el usuario
     * ve en el dropdown), no como slugs crudos.
     */
    public function test_invalid_org_role_message_lists_display_names(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);

        $import = new UsersImport();

        $rows = new Collection([
            new Collection([
                'nombre' => 'Ana',
                'apellido' => 'Torres',
                'email' => 'ana.torres@example.com',
                'tipo_documento' => 'dni',
                'numero_documento' => '12345678',
                'estado' => 'active',
                'org1_ruc' => $tenant->ruc,
                'org1_rol' => 'gerente',
            ]),
        ]);

        $import->collection($rows);

        $roleError = collect($import->getErrors())->first(fn($error) => $error['field'] === 'org1_rol');

        $this->assertNotNull($roleError);
        $this->assertStringContainsString("'gerente'", $roleError['message']);
        $this->assertStringContainsString('Empleado', $roleError['message']);
        $this->assertStringNotContainsString('client', $roleError['message']);
        $this->assertEmpty($import->getParsedData());
    }
}
