<?php

namespace App\Exports\Sheets;

use App\Models\NetworkLeg;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class NetworkLegsSheet implements
    FromCollection,
    WithHeadings,
    WithTitle,
    WithStyles,
    ShouldAutoSize,
    WithColumnFormatting
{
    public function __construct(
        private readonly Collection $legs
    ) {}

    public function title(): string
    {
        return 'Jalur Observasi';
    }

    public function collection(): Enumerable
    {
        return $this->legs->map(function (NetworkLeg $leg, int $index) {
            $dh_adj  = $leg->corrected_delta_h !== null ? (float) $leg->corrected_delta_h : (float) $leg->observed_delta_h;
            $corrMm  = $leg->corrected_delta_h !== null ? ((float) $leg->corrected_delta_h - (float) $leg->observed_delta_h) * 1000 : 0.0;
            $resMm   = (float) ($leg->residual ?? 0) * 1000;
            $distKm  = (float) $leg->distance_m / 1000;
            $weight  = $distKm > 0 ? round(1 / $distKm, 6) : 0;

            return [
                'no'            => $index + 1,
                'from_point'    => $leg->from_point,
                'to_point'      => $leg->to_point,
                'obs_dh'        => round((float) $leg->observed_delta_h, 4),
                'distance_km'   => round((float) $leg->distance_m / 1000, 4),
                'weight'        => $weight,
                'adj_dh'        => round($dh_adj, 4),
                'correction_mm' => round($corrMm, 3),
                'residual_mm'   => round($resMm, 3),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Dari Titik',
            'Ke Titik',
            'ΔH Observasi (m)',
            'Jarak (km)',
            'Bobot (1/d)',
            'ΔH Terkoreksi (m)',
            'Koreksi (mm)',
            'Residual (mm)',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'D' => NumberFormat::FORMAT_NUMBER_00,   // obs_dh  — 4 decimal via custom below
            'E' => NumberFormat::FORMAT_NUMBER_00,
            'F' => NumberFormat::FORMAT_NUMBER_00,
            'G' => NumberFormat::FORMAT_NUMBER_00,
            'H' => NumberFormat::FORMAT_NUMBER_00,
            'I' => NumberFormat::FORMAT_NUMBER_00,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->legs->count() + 1; // +1 for header

        // Header row
        $sheet->getStyle('A1:I1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1D4ED8'],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows — center all
        $sheet->getStyle('A2:I' . ($lastRow + 1))->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Left-align text columns
        $sheet->getStyle('B2:C' . ($lastRow + 1))->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        // Stripe even rows
        for ($row = 2; $row <= $lastRow + 1; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle('A' . $row . ':I' . $row)->applyFromArray([
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'EFF6FF'],
                    ],
                ]);
            }
        }

        // Freeze header
        $sheet->freezePane('A2');

        return [];
    }
}