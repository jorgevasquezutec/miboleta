<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'root',
                'display_name' => 'Super Administrador',
                'description' => 'Acceso total al sistema, puede gestionar todos los tenants y usuarios',
                'guard_name' => 'web',
                'permissions' => [
                    'manage_tenants',
                    'manage_users',
                    'manage_roles',
                    'manage_documents',
                    'manage_vacations',
                    'view_reports',
                    'system_configuration',
                ],
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrador de Tenant',
                'description' => 'Administrador con acceso completo dentro de su organización',
                'guard_name' => 'web',
                'permissions' => [
                    'manage_users',
                    'upload_documents',
                    'manage_documents',
                    'approve_vacations',
                    'view_reports',
                    'tenant_configuration',
                ],
            ],
            [
                'name' => 'client',
                'display_name' => 'Cliente',
                'description' => 'Usuario final con permisos básicos',
                'guard_name' => 'web',
                'permissions' => [
                    'view_own_documents',
                    'sign_documents',
                    'request_vacation',
                    'view_own_vacation_requests',
                ],
            ],
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }
    }
}
