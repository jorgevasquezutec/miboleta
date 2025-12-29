<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = Role::all()->keyBy('name');
        $tenants = Tenant::all();

        // 1. Usuario ROOT (sin tenant)
        $root = User::create([
            'name' => 'Root',
            'last_name' => 'Admin',
            'email' => 'admin@email.com',
            'password' => Hash::make('password'),
            'document_type' => null,
            'document_text' => null,
            'phone' => '+51 999 999 999',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $root->roles()->attach($roles['root'], ['granted_by' => $root->id, 'granted_at' => now()]);

        // 2. Admin para Corporación ABC
        $adminABC = User::create([
            'name' => 'Carlos',
            'last_name' => 'Administrador',
            'email' => 'admin@corporacionabc.com',
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => '12345678',
            'phone' => '+51 987 654 321',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $adminABC->roles()->attach($roles['admin'], ['granted_by' => $root->id, 'granted_at' => now()]);
        $adminABC->tenants()->attach($tenants[0]->id, ['is_primary' => true]);

        // 3. Cliente para Corporación ABC
        $clientABC = User::create([
            'name' => 'Juan',
            'last_name' => 'Pérez García',
            'email' => 'juan.perez@corporacionabc.com',
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => '87654321',
            'phone' => '+51 999 111 222',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $clientABC->roles()->attach($roles['client'], ['granted_by' => $adminABC->id, 'granted_at' => now()]);
        $clientABC->tenants()->attach($tenants[0]->id, ['is_primary' => true]);

        // 4. Admin para Empresa XYZ
        $adminXYZ = User::create([
            'name' => 'María',
            'last_name' => 'Rodríguez',
            'email' => 'admin@empresaxyz.com',
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => '11223344',
            'phone' => '+51 988 777 666',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $adminXYZ->roles()->attach($roles['admin'], ['granted_by' => $root->id, 'granted_at' => now()]);
        $adminXYZ->tenants()->attach($tenants[1]->id, ['is_primary' => true]);

        // 5. Cliente para Empresa XYZ
        $clientXYZ = User::create([
            'name' => 'Pedro',
            'last_name' => 'López Sánchez',
            'email' => 'pedro.lopez@empresaxyz.com',
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => '44332211',
            'phone' => '+51 999 333 444',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $clientXYZ->roles()->attach($roles['client'], ['granted_by' => $adminXYZ->id, 'granted_at' => now()]);
        $clientXYZ->tenants()->attach($tenants[1]->id, ['is_primary' => true]);

        // 6. Usuario multi-tenant (pertenece a 2 empresas)
        $multiTenant = User::create([
            'name' => 'Ana',
            'last_name' => 'Torres Martínez',
            'email' => 'ana.torres@email.com',
            'password' => Hash::make('password'),
            'document_type' => 'dni',
            'document_text' => '55667788',
            'phone' => '+51 999 555 666',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $multiTenant->roles()->attach($roles['client'], ['granted_by' => $root->id, 'granted_at' => now()]);
        $multiTenant->tenants()->attach($tenants[0]->id, ['is_primary' => true]);
        $multiTenant->tenants()->attach($tenants[1]->id, ['is_primary' => false]);
    }
}
