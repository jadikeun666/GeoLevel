<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;

class NetworkAdjustmentSheet implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithStyles,
    ShouldAutoSize
{
    /**
     * @param Collection<int, array{
     *   point_name: string,
     *   is_benchmark: bool,
     *   initial_elevation: float,
     *   correction: float,
     *   adjusted_elevation: float,
     *   std_dev_mm: float|null,
     * }> $adjustedPoints
     */
    public function __construct(
        private readonly Collection $adjustedPoints
    ) {}

    public function title(): string
    {
        return 'Elevasi Terkoreksi';
    }

    public function collection(): Enumerable
    {
        return $this->adjustedPoints->map(function (array $pt, int $index) {
            $corrMm = (float) ($pt['correction'] ?? 0) * 1000;
            $isBM   = $pt['is_benchmark'] ?? false;

            return [
                'no'                => $index + 1,
                'point_name'        => $pt['point_name'],
                'is_benchmark'      => $isBM ? 'Ya' : 'Tidak',
                'initial_elevation' => round((float) $pt['initial_elevation'], 4),
                'correction_mm'     => $isBM ? null : round($corrMm, 3),
                'adjusted_elevation'=> round((float) $pt['adjusted_elevation'], 4),
                'std_dev_mm'        => isset($pt['std_dev_mm'])
                    ? round((float) $pt['std_dev_mm'], 3)
                    : null,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Titik',
            'Benchmark?',
            'Elevasi Awal (m)',
            'Koreksi (mm)',
            'Elevasi Terkoreksi (m)',
            'Std. Deviasi (mm)',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->adjustedPoints->count() + 1;

        // Header
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '065F46'],   // green for adjustment sheet
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data alignment
        $sheet->getStyle('A2:G' . ($lastRow + 1))->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('B2:B' . ($lastRow + 1))->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Stripe
        for ($row = 2; $row <= $lastRow + 1; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'ECFDF5'],
                    ],
                ]);
            }
        }

        // Highlight adjusted_elevation column
        $sheet->getStyle('F2:F' . ($lastRow + 1))->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
        ]);

        $sheet->freezePane('A2');

        return [];
    }
}