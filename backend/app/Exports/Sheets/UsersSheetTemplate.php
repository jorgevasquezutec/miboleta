<?php

namespace App\Exports\Sheets;

use App\Services\BulkUserUploadService;
use App\Support\BulkUserColumns;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class UsersSheetTemplate implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths, WithEvents
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
            // Display name (Obs 2), no el slug crudo: es lo que el usuario
            // ve en el dropdown de esta columna (ver ValidationRulesSheet).
            // El import acepta ambos (ver BulkUserUploadService::orgRoleSlug).
            'org1_rol' => BulkUserUploadService::orgRoleLabel('client'),
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

        // Formato de texto en las columnas de texto forzado (numero_documento,
        // telefono), de la fila 2 al final del rango editable (Obs 4). El
        // formato de celda es lo que hace que Excel/LibreOffice traten lo
        // tipeado como texto en vez de convertirlo a número y comerse los
        // ceros a la izquierda de un documento como "01234567".
        foreach (BulkUserColumns::textKeys($this->maxOrganizations) as $key) {
            $letter = BulkUserColumns::letterFor($key, $this->maxOrganizations);
            $sheet->getStyle("{$letter}2:{$letter}{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
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

    /**
     * Fuerza a texto las celdas de la fila de ejemplo en las columnas de
     * texto forzado (ver BulkUserColumns::textKeys). El formato de celda
     * (NumberFormat::FORMAT_TEXT en styles()) es solo cosmético: NO cambia
     * el tipo de dato que PhpSpreadsheet ya decidió guardar al leer un valor
     * "que parece número" desde FromArray (DefaultValueBinder). Sin este
     * evento, el ejemplo '12345678' de numero_documento se guarda como
     * número igual, contradiciendo la instrucción de la plantilla de
     * escribir el documento como texto (Obs 4). Se aplica SOLO a estas
     * columnas y solo en la fila 2: usar un StringValueBinder global
     * rompería las fechas de ejemplo (fecha_nacimiento, org1_fecha_ingreso).
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach (BulkUserColumns::textKeys($this->maxOrganizations) as $key) {
                    $letter = BulkUserColumns::letterFor($key, $this->maxOrganizations);
                    $coordinate = "{$letter}2";
                    $value = $sheet->getCell($coordinate)->getValue();

                    if ($value !== null && $value !== '') {
                        $sheet->setCellValueExplicit($coordinate, (string) $value, DataType::TYPE_STRING);
                    }
                }
            },
        ];
    }
}
