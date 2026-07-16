<?php

namespace App\Exports\Sheets;

use App\Support\BulkUserColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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
     * Encabezados legibles de las columnas (dinámicos según maxOrganizations).
     * El orden y los textos salen de BulkUserColumns, que es también quien le
     * enseña al importador a traducirlos de vuelta a claves canónicas.
     */
    public function headings(): array
    {
        return BulkUserColumns::labels($this->maxOrganizations);
    }

    /**
     * Datos iniciales: una fila de ejemplo + 4 vacías.
     */
    public function array(): array
    {
        $examples = [$this->exampleRow()];

        for ($j = 0; $j < 4; $j++) {
            $examples[] = array_fill(0, count($this->headings()), '');
        }

        return $examples;
    }

    /**
     * Fila de ejemplo. Se arma por clave canónica y se ordena al final según
     * el layout, para no depender de la posición de cada columna.
     *
     * Las fechas se escriben como número de serie de Excel, no como texto:
     * el value binder por defecto de Laravel Excel no interpreta un string
     * 'Y-m-d' como fecha, así que un texto quedaría como texto y la celda
     * incumpliría la validación de fecha de su propia columna.
     */
    private function exampleRow(): array
    {
        $values = [
            'nombre' => 'Juan',
            'apellido' => 'Pérez García',
            'email' => 'juan.perez@ejemplo.com',
            'tipo_documento' => 'dni',
            'numero_documento' => '12345678',
            'estado' => 'active',
            'telefono' => '+51 999 999 999',
            'fecha_nacimiento' => ExcelDate::PHPToExcel(new \DateTimeImmutable('1990-05-15')),
            // Solo la primera organización lleva ejemplo; el resto queda en
            // blanco para que se vea que las demás son opcionales.
            'org1_ruc' => $this->organizations[0]['ruc'] ?? '',
            'org1_rol' => 'client',
            'org1_fecha_ingreso' => ExcelDate::PHPToExcel(now()->subYear()->startOfDay()),
            'org1_saldo_vacaciones' => '15',
            'org1_departamento' => 'Sistemas',
            'org1_cargo' => 'Analista Programador',
        ];

        return array_map(
            fn(string $key) => $values[$key] ?? '',
            BulkUserColumns::keys($this->maxOrganizations)
        );
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

        // Formato de fecha en las columnas de fecha, de la fila 2 al final del
        // rango editable. No es solo cosmético: una celda con formato de fecha
        // hace que Excel/LibreOffice conviertan a fecha lo que el usuario
        // tipea, en vez de dejarlo como texto (y el backend, que acepta ambos,
        // recibe así un valor sin ambigüedad de formato ni de locale).
        $lastRow = BulkUserColumns::MAX_USER_ROWS;
        foreach (BulkUserColumns::dateKeys($this->maxOrganizations) as $key) {
            $letter = BulkUserColumns::letterFor($key, $this->maxOrganizations);
            $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_DATE_YYYYMMDD);
        }

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
        return BulkUserColumns::widths($this->maxOrganizations);
    }
}
