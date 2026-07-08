<?php

namespace App\Exports\Sheets;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class NetworkSummarySheet implements FromCollection, WithTitle, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly Project    $project,
        private readonly array      $stats
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function collection(): Enumerable
    {
        $fhMm         = ($this->project->closure_error ?? 0) * 1000;
        $tolMm        = ($this->project->allowed_tolerance ?? 0) * 1000;
        $sigma0Mm     = $this->stats['sigma0_mm'] ?? 0;
        $vtpv         = $this->stats['vtpv'] ?? 0;
        $varFactor    = $this->stats['variance_factor'] ?? 0;
        $redundancy   = $this->stats['redundancy'] ?? 0;
        $converged    = $this->stats['converged'] ?? false;
        $iterations   = $this->stats['iterations'] ?? 0;

        $rows = collect([
            ['INFORMASI PROYEK', ''],
            ['Nama Proyek',         $this->project->name],
            ['Lokasi',              $this->project->location],
            ['Tanggal Survei',      optional($this->project->survey_date)->format('d/m/Y')],
            ['Benchmark',           $this->project->benchmark_name . ' = ' . number_format((float) $this->project->benchmark_elevation, 4) . ' m'],
            ['Kelas Toleransi',     strtoupper($this->project->tolerance_class)],
            ['Status Proyek',       strtoupper($this->project->status ?? 'draft')],
            ['', ''],

            ['PARAMETER JARING', ''],
            ['Jumlah Jalur (n)',             $this->stats['n_legs']],
            ['Jumlah Titik Unik',            $this->stats['n_points']],
            ['Jumlah Unknown (u)',           $this->stats['n_unknowns'] ?? ($this->stats['n_points'] - 1)],
            ['Derajat Kebebasan (r = n - u)',$redundancy],
            ['Total Jarak Jaring',           number_format($this->stats['total_distance_km'] ?? 0, 4) . ' km'],
            ['', ''],

            ['HASIL PERATAAN', ''],
            ['Kesalahan Penutup (fh)',        number_format($fhMm, 3) . ' mm'],
            ['Toleransi Diijinkan',           number_format($tolMm, 3) . ' mm'],
            ['Status Toleransi',              $fhMm <= $tolMm ? 'LOLOS' : 'TIDAK LOLOS'],
            ['', ''],
            ['VTPV (Σ Residual Berbobot²)',   number_format($vtpv, 6)],
            ['Variance Factor (σ₀²)',         number_format($varFactor, 6)],
            ['Kesalahan Baku Referensi (σ₀)', number_format($sigma0Mm, 4) . ' mm'],
            ['Evaluasi Kualitas',             $sigma0Mm <= 5 ? 'Baik (≤5 mm)' : ($sigma0Mm <= 10 ? 'Cukup (5–10 mm)' : 'Perlu diperiksa (>10 mm)')],
            ['', ''],

            ['KONVERGENSI ITERASI', ''],
            ['Status Konvergensi', $converged ? 'Konvergen' : 'Tidak Konvergen'],
            ['Jumlah Iterasi',     $iterations],
            ['Iterasi Maksimum',   $this->stats['max_iterations'] ?? 'N/A'],
            ['', ''],

            ['METADATA EKSPOR', ''],
            ['Digenerate oleh',  'GeoLevel v1.0'],
            ['Waktu Generate',   now()->format('d/m/Y H:i:s')],
            ['Standar Referensi','SNI 19-6988-2004'],
        ]);

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        // Section headers — rows containing all-caps labels with empty value
        $sections = [1, 9, 15, 30, 35];  // approximate row positions (0-based + 1)

        // We apply styles by content — iterate and check
        $highestRow = $sheet->getHighestRow();

        for ($row = 1; $row <= $highestRow; $row++) {
            $cellA = $sheet->getCell('A' . $row)->getValue();
            $cellB = $sheet->getCell('B' . $row)->getValue();

            // Section title: non-empty A, empty B, all uppercase
            if (!empty($cellA) && empty($cellB) && $cellA === strtoupper($cellA)) {
                $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1D4ED8'],
                    ],
                ]);
                // Merge A & B for section title
                $sheet->mergeCells('A' . $row . ':B' . $row);
                $sheet->getStyle('A' . $row)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);
                continue;
            }

            // Empty row separator
            if (empty($cellA) && empty($cellB)) {
                continue;
            }

            // Data rows: label in A, value in B
            $sheet->getStyle('A' . $row)->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => '374151']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getStyle('B' . $row)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);

            // Highlight specific status values
            $value = (string) $cellB;
            if (in_array($value, ['LOLOS', 'Konvergen', 'Baik (≤5 mm)'])) {
                $sheet->getStyle('B' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '065F46']],
                ]);
            } elseif (in_array($value, ['TIDAK LOLOS', 'Tidak Konvergen'])) {
                $sheet->getStyle('B' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '991B1B']],
                ]);
            } elseif (str_contains($value, 'Cukup') || str_contains($value, 'Perlu')) {
                $sheet->getStyle('B' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'B45309']],
                ]);
            }
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(38);
        $sheet->getColumnDimension('B')->setWidth(42);

        return [];
    }
}