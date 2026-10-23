<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperationalReportExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    public function __construct(private readonly array $headings, private readonly array $rows) {}

    public function array(): array { return $this->rows; }
    public function headings(): array { return $this->headings; }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()->setFillType('solid')->getStartColor()->setRGB('EAF4F5');
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true)->setVertical('top');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        return [];
    }
}
