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

class OrganizationsCatalogSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    private array $organizations;

    public function __construct(array $organizations)
    {
        $this->organizations = $organizations;
    }

    public function title(): string
    {
        return 'Catálogo_Organizaciones';
    }

    public function headings(): array
    {
        return [
            'RUC',
            'Nombre Empresa',
            'Estado',
            'Supervisores Disponibles',
        ];
    }

    public function array(): array
    {
        $data = [];

        foreach ($this->organizations as $org) {
            // Contar supervisores (esto se puede mejorar con una query)
            $supervisorsCount = \App\Models\User::whereHas('tenants', function ($q) use ($org) {
                $q->where('tenants.id', $org['id']);
            })
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'admin');
                })
                ->count();

            $data[] = [
                $org['ruc'] ?? '',
                $org['name'] ?? '',
                ($org['status'] ?? 'inactive') === 'active' ? 'Activa' : 'Inactiva',
                $supervisorsCount,
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet)
    {
        // Header style
        $sheet->getStyle('A1:D1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '70AD47'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // Convertir en tabla
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:D1');

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // RUC
            'B' => 35, // Nombre
            'C' => 12, // Estado
            'D' => 22, // Supervisores
        ];
    }
}
