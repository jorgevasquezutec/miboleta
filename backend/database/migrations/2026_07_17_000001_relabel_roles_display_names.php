<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra los display_name de los roles operativos según la terminología
 * pedida por el cliente (los slugs internos NO cambian; tampoco permisos ni
 * Gates — la autorización real vive en config/access_matrix.php):
 *
 *   client       Cliente / Usuario                    -> Empleado
 *   admin        Administrador / Administrador de Tenant -> Admin Empleados
 *   admin_tenant Administrador de Empresa (Tenant)     -> Admin Clientes
 *   aprobador    Aprobador                            -> Aprobador Empleado
 *
 * El formulario "Roles en esta empresa" pinta role.display_name traído de la
 * BD (GET /api/roles), así que este UPDATE es lo que realmente cambia lo que
 * ve el usuario en BDs existentes. Se filtra por 'name' (slug), por lo que es
 * idempotente y funciona tanto en BDs viejas como nuevas. 'root' no se toca.
 */
return new class extends Migration
{
    /** slug => [nuevo display_name, display_name previo (para down)] */
    private const RELABEL = [
        'client'       => ['Empleado',           'Cliente'],
        'admin'        => ['Admin Empleados',     'Administrador'],
        'admin_tenant' => ['Admin Clientes',      'Administrador de Empresa (Tenant)'],
        'aprobador'    => ['Aprobador Empleado',  'Aprobador'],
    ];

    public function up(): void
    {
        foreach (self::RELABEL as $name => [$new, $old]) {
            DB::table('roles')
                ->where('name', $name)
                ->update(['display_name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::RELABEL as $name => [$new, $old]) {
            DB::table('roles')
                ->where('name', $name)
                ->update(['display_name' => $old, 'updated_at' => now()]);
        }
    }
};
