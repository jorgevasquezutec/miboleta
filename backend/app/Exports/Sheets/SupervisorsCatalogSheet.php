<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SupervisorsCatalogSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    private array $supervisorsByOrg;
    private array $organizations;

    public function __construct(array $supervisorsByOrg, array $organizations)
    {
        $this->supervisorsByOrg = $supervisorsByOrg;
        $this->organizations = $organizations;
    }

    public function title(): string
    {
        return 'Catálogo_Supervisores';
    }

    public function headings(): array
    {
        return [
            'Empresa (RUC)',
            'Nombre Empresa',
            'Email Supervisor',
            'Nombre Completo',
            'Estado',
        ];
    }

    public function array(): array
    {
        $data = [];

        // Crear un map de RUC a nombre para lookup rápido
        $orgMap = [];
        foreach ($this->organizations as $org) {
            $orgMap[$org['id']] = [
                'ruc' => $org['ruc'] ?? '',
                'name' => $org['name'] ?? '',
            ];
        }

        // Iterar por cada organización y sus supervisores
        foreach ($this->supervisorsByOrg as $orgId => $supervisors) {
            $orgInfo = $orgMap[$orgId] ?? null;

            if (!$orgInfo) {
                continue;
            }

            if (empty($supervisors)) {
                // Si no hay supervisores, mostrar una fila indicándolo
                $data[] = [
                    $orgInfo['ruc'],
                    $orgInfo['name'],
                    '(Sin supervisores)',
                    '',
                    'N/A',
                ];
            } else {
                // Agregar cada supervisor
                foreach ($supervisors as $supervisor) {
                    $data[] = [
                        $orgInfo['ruc'],
                        $orgInfo['name'],
                        $supervisor['email'],
                        $supervisor['full_name'],
                        'Activo',
                    ];
                }
            }
        }

        // Si no hay datos, agregar fila informativa
        if (empty($data)) {
            $data[] = [
                '',
                'No hay supervisores disponibles',
                '',
                '',
                '',
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:E1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'ED7D31'], // Naranja
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Freeze y filtro
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:E1');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // RUC
            'B' => 30, // Empresa
            'C' => 30, // Email
            'D' => 25, // Nombre
            'E' => 10, // Estado
        ];
    }
}
