<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige el display_name obsoleto del rol 'admin' en bases preexistentes.
 *
 * El seeder original (commit 66dec8c) sembraba 'admin' con display_name
 * 'Administrador de Tenant'. El sprint-fix multitenant (2bc0182) lo renombró
 * a 'Administrador' y agregó el rol nuevo 'admin_tenant' =
 * 'Administrador de Empresa (Tenant)'. Pero RoleSeeder solo corre en la
 * instalación inicial y insert_missing_roles nunca pisa filas existentes, así
 * que en BDs preexistentes 'admin' quedó congelado con el label viejo.
 *
 * Resultado en el formulario "Roles en esta empresa": aparecían dos etiquetas
 * casi idénticas — 'Administrador de Tenant' (admin) y
 * 'Administrador de Empresa (Tenant)' (admin_tenant) — que confundían al
 * cliente. Este UPDATE alinea el label con el seeder actual. Es idempotente:
 * el WHERE por (name, display_name) no encuentra filas si ya está corregido o
 * si la BD se sembró nueva con el nombre correcto. No toca permisos ni Gates
 * (la autorización real vive en config/access_matrix.php).
 */
return new class extends Migration
{
    private const OLD_DISPLAY_NAME = 'Administrador de Tenant';
    private const NEW_DISPLAY_NAME = 'Administrador';

    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'admin')
            ->where('display_name', self::OLD_DISPLAY_NAME)
            ->update([
                'display_name' => self::NEW_DISPLAY_NAME,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'admin')
            ->where('display_name', self::NEW_DISPLAY_NAME)
            ->update([
                'display_name' => self::OLD_DISPLAY_NAME,
                'updated_at' => now(),
            ]);
    }
};
