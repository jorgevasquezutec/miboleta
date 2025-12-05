<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Corporación ABC',
                'ruc' => '20123456789',
                'business_name' => 'Corporación ABC S.A.C.',
                'address' => 'Av. Principal 123, Lima, Perú',
                'phone' => '+51 1 234-5678',
                'status' => 'active',
            ],
            [
                'name' => 'Empresa XYZ',
                'ruc' => '20987654321',
                'business_name' => 'Empresa XYZ E.I.R.L.',
                'address' => 'Jr. Comercio 456, Lima, Perú',
                'phone' => '+51 1 987-6543',
                'status' => 'active',
            ],
            [
                'name' => 'Tech Solutions',
                'ruc' => '20555666777',
                'business_name' => 'Tech Solutions Peru S.A.',
                'address' => 'Av. Tecnología 789, San Isidro, Lima',
                'phone' => '+51 1 555-6677',
                'status' => 'active',
            ],
        ];

        foreach ($tenants as $tenantData) {
            Tenant::create($tenantData);
        }
    }
}
