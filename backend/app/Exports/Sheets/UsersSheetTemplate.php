<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class UsersSheetTemplate implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    private int $maxOrganizations;
    private array $organizations;

    public function __construct(int $maxOrganizations, array $organizations)
    {
        $this->maxOrganizations = $maxOrganizations;
        $this->organizations = $organizations;
    }

    /**
     * Nombre de la hoja
     */
    public function title(): string
    {
        return 'Usuarios';
    }

    /**
     * Encabezados de columnas (dinámicos según maxOrganizations)
     */
    public function headings(): array
    {
        $baseHeaders = [
            'nombre',
            'apellido',
            'email',
            'tipo_documento',
            'numero_documento',
            'rol',
            'estado',
            'telefono',
        ];

        // Agregar columnas de organizaciones dinámicamente (RUC + supervisor,
        // posiciones sin cambios respecto al formato anterior)
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $baseHeaders[] = "org{$i}_ruc";
            $baseHeaders[] = "org{$i}_supervisor_email";
        }

        // Bloque nuevo (RP1-C): rol(es), fecha de ingreso y saldo inicial de
        // vacaciones por organización. Se agrega DESPUÉS del bloque anterior
        // para no correr las columnas org{n}_ruc/org{n}_supervisor_email ya
        // existentes.
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $baseHeaders[] = "org{$i}_rol";
            $baseHeaders[] = "org{$i}_fecha_ingreso";
            $baseHeaders[] = "org{$i}_saldo_vacaciones";
        }

        // Bloque nuevo (RP-B3): departamento/cargo por organización. Igual
        // que el bloque anterior, se agrega al final para no correr las
        // columnas ya existentes.
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $baseHeaders[] = "org{$i}_departamento";
            $baseHeaders[] = "org{$i}_cargo";
        }

        // P1: fecha de nacimiento del usuario (campo de users, no por
        // organización). Se agrega AL FINAL para no correr las columnas ya
        // existentes.
        $baseHeaders[] = 'fecha_nacimiento';

        return $baseHeaders;
    }

    /**
     * Datos iniciales (5 filas de ejemplo)
     */
    public function array(): array
    {
        $examples = [];

        // Fila de ejemplo 1
        $row1 = [
            'Juan',
            'Pérez García',
            'juan.perez@ejemplo.com',
            'dni',
            '12345678',
            'client',
            'active',
            '+51 999 999 999',
        ];

        // Agregar org placeholders
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            if ($i === 1 && count($this->organizations) > 0) {
                // Primera org con ejemplo
                $row1[] = $this->organizations[0]['ruc'] ?? '';
                $row1[] = ''; // Supervisor opcional
            } else {
                $row1[] = '';
                $row1[] = '';
            }
        }

        // Ejemplo de rol/fecha de ingreso/saldo para la primera organización
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            if ($i === 1) {
                $row1[] = 'client';
                $row1[] = now()->subYear()->format('Y-m-d');
                $row1[] = '15';
            } else {
                $row1[] = '';
                $row1[] = '';
                $row1[] = '';
            }
        }

        // Ejemplo de departamento/cargo para la primera organización
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            if ($i === 1) {
                $row1[] = 'Sistemas';
                $row1[] = 'Analista Programador';
            } else {
                $row1[] = '';
                $row1[] = '';
            }
        }

        // P1: ejemplo de fecha de nacimiento (campo de users, al final)
        $row1[] = '1990-05-15';

        $examples[] = $row1;

        // 4 filas vacías más
        for ($j = 0; $j < 4; $j++) {
            $emptyRow = array_fill(0, count($this->headings()), '');
            $examples[] = $emptyRow;
        }

        return $examples;
    }

    /**
     * Estilos de la hoja
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo del header
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Freeze primera fila
        $sheet->freezePane('A2');

        // Auto-filtro
        $sheet->setAutoFilter('A1:' . $sheet->getHighestColumn() . '1');

        return [];
    }

    /**
     * Anchos de columnas
     */
    public function columnWidths(): array
    {
        $widths = [
            'A' => 15, // nombre
            'B' => 20, // apellido
            'C' => 30, // email
            'D' => 18, // tipo_documento
            'E' => 18, // numero_documento
            'F' => 15, // rol
            'G' => 12, // estado
            'H' => 18, // telefono
        ];

        // Agregar anchos para columnas de org (bloque ruc/supervisor)
        $col = 'I';
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $widths[$col++] = 15; // org_ruc
            $widths[$col++] = 30; // org_supervisor_email
        }

        // Anchos para el bloque nuevo: rol/fecha_ingreso/saldo_vacaciones
        $colIndex = 8 + (2 * $this->maxOrganizations) + 1;
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 22; // org_rol
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 18; // org_fecha_ingreso
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 18; // org_saldo_vacaciones
        }

        // Anchos para el bloque nuevo: departamento/cargo (RP-B3)
        for ($i = 1; $i <= $this->maxOrganizations; $i++) {
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 22; // org_departamento
            $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 22; // org_cargo
        }

        // P1: ancho para fecha_nacimiento (columna final, campo de users)
        $widths[Coordinate::stringFromColumnIndex($colIndex++)] = 18; // fecha_nacimiento

        return $widths;
    }
}
