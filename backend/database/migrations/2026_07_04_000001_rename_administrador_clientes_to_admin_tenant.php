<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nombre/display_name/description/permissions "antiguos" (pre-jerarquía
     * admin_tenant) del rol, usados por down() para revertir el rename.
     */
    private const OLD_NAME = 'administrador_clientes';
    private const OLD_DISPLAY_NAME = 'Administrador de clientes';
    private const OLD_DESCRIPTION = 'Usuario con permisos para gestionar usuarios y documentos de clientes dentro de su empresa';
    private const OLD_PERMISSIONS = ['manage_users', 'upload_documents', 'manage_documents', 'view_reports'];

    private const NEW_NAME = 'admin_tenant';
    private const NEW_DISPLAY_NAME = 'Administrador de Empresa (Tenant)';
    private const NEW_DESCRIPTION = 'Administrador de la empresa (tenant), con permisos superiores a Admin: gestiona usuarios (incluidos Admin y Aprobador), documentos, vacaciones, reportes y configuración de su empresa';
    private const NEW_PERMISSIONS = [
        'manage_users',
        'upload_documents',
        'manage_documents',
        'approve_vacations',
        'view_reports',
        'tenant_configuration',
    ];

    /**
     * Run the migrations.
     *
     * Renombra el rol 'administrador_clientes' a 'admin_tenant' y eleva sus
     * permisos por encima de 'admin' (ver RoleSeeder). Un simple UPDATE por
     * nombre es idempotente: si ya se corrió (o el rol fue creado
     * directamente como 'admin_tenant' vía seeder en un entorno nuevo), el
     * WHERE no encuentra filas y no hace nada.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('name', self::OLD_NAME)
            ->update([
                'name' => self::NEW_NAME,
                'display_name' => self::NEW_DISPLAY_NAME,
                'description' => self::NEW_DESCRIPTION,
                'permissions' => json_encode(self::NEW_PERMISSIONS),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('name', self::NEW_NAME)
            ->update([
                'name' => self::OLD_NAME,
                'display_name' => self::OLD_DISPLAY_NAME,
                'description' => self::OLD_DESCRIPTION,
                'permissions' => json_encode(self::OLD_PERMISSIONS),
                'updated_at' => now(),
            ]);
    }
};
