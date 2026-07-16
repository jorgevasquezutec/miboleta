<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Inserta los roles del catálogo que puedan faltar en la BD (aditivo).
 *
 * RoleSeeder solo corre en la primera instalación (RUN_SEEDERS=true); en
 * deploys subsecuentes solo corre `migrate --force`. Roles recientes como
 * `aprobador` y `admin_tenant` pueden no existir en bases preexistentes,
 * lo que rompería cualquier asignación de esos roles. Esta migración los
 * completa con firstOrCreate manual, sin tocar nunca filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            [
                'name' => 'root',
                'display_name' => 'Super Administrador',
                'description' => 'Acceso total al sistema, puede gestionar todos los tenants y usuarios',
                'guard_name' => 'web',
                'permissions' => ['manage_tenants', 'manage_users', 'manage_roles', 'manage_documents', 'manage_vacations', 'view_reports', 'system_configuration'],
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrador',
                'description' => 'Administrador con acceso completo dentro de su organización',
                'guard_name' => 'web',
                'permissions' => ['manage_users', 'upload_documents', 'manage_documents', 'approve_vacations', 'view_reports', 'tenant_configuration'],
            ],
            [
                'name' => 'client',
                'display_name' => 'Cliente',
                'description' => 'Usuario final con permisos básicos',
                'guard_name' => 'web',
                'permissions' => ['view_own_documents', 'sign_documents', 'request_vacation', 'view_own_vacation_requests'],
            ],
            [
                'name' => 'aprobador',
                'display_name' => 'Aprobador',
                'description' => 'Usuario con permisos para aprobar solicitudes de vacaciones dentro de su empresa',
                'guard_name' => 'web',
                'permissions' => ['approve_vacations', 'view_reports', 'view_own_documents', 'sign_documents', 'request_vacation', 'view_own_vacation_requests'],
            ],
            [
                'name' => 'admin_tenant',
                'display_name' => 'Administrador de Empresa (Tenant)',
                'description' => 'Administrador de la empresa (tenant), con permisos superiores a Admin: gestiona usuarios (incluidos Admin y Aprobador), documentos, vacaciones, reportes y configuración de su empresa',
                'guard_name' => 'web',
                'permissions' => ['manage_users', 'upload_documents', 'manage_documents', 'approve_vacations', 'view_reports', 'tenant_configuration'],
            ],
        ];

        $now = now();
        foreach ($roles as $role) {
            $exists = DB::table('roles')->where('name', $role['name'])->exists();
            if ($exists) {
                continue; // nunca pisar filas existentes
            }

            DB::table('roles')->insert([
                'name' => $role['name'],
                'display_name' => $role['display_name'],
                'description' => $role['description'],
                'guard_name' => $role['guard_name'],
                'permissions' => json_encode($role['permissions']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: borrar un rol en uso rompería las FKs de user_roles /
        // user_tenant_roles. Un alta de catálogo no se revierte.
    }
};
