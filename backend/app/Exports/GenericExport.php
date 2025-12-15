<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GenericExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $data;
    protected array $headings;

    public function __construct(Collection $data, array $headings = [])
    {
        $this->data = $data;
        $this->headings = $headings;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        // If headings are provided, use them; otherwise, get from first row
        if (!empty($this->headings)) {
            return $this->headings;
        }

        $first = $this->data->first();
        if ($first) {
            return array_keys(is_array($first) ? $first : $first->toArray());
        }

        return [];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Style the first row as bold (headers)
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }
}
