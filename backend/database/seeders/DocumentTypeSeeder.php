<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'boleta_remuneraciones',
                'display_name' => 'Boleta de Remuneraciones',
                'description' => 'Boleta mensual de remuneraciones',
                'requires_signature' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'liquidacion_cts',
                'display_name' => 'Hoja Liquidación de CTS',
                'description' => 'Compensación por Tiempo de Servicios',
                'requires_signature' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'liquidacion_utilidades',
                'display_name' => 'Hoja Liquidación de Participación de Utilidades',
                'description' => 'Participación en utilidades',
                'requires_signature' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'certificado_quinta',
                'display_name' => 'Certificado de Rentas de Quinta Categoría',
                'description' => 'Certificado de retenciones de quinta categoría',
                'requires_signature' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'contrato_trabajo',
                'display_name' => 'Contrato de Trabajo',
                'description' => 'Contrato laboral',
                'requires_signature' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'legajo_personal',
                'display_name' => 'Legajo de Personal',
                'description' => 'Documentos del legajo personal del empleado',
                'requires_signature' => false,
                'sort_order' => 6,
            ],
        ];

        // Deshabilitar FK checks, truncar, y volver a habilitar
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DocumentType::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        foreach ($types as $type) {
            DocumentType::create($type);
        }
    }
}
