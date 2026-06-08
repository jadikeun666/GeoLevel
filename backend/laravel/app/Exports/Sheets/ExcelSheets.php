<?php
// ─── app/Exports/Sheets/ElevasiSheet.php ───────────────────────────────────

namespace App\Exports\Sheets;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ElevasiSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public readonly Project $project) {}

    public function title(): string { return 'Elevasi'; }

    public function headings(): array
    {
        return ['No Urut', 'Titik', 'HI', 'Elevasi Sementara', 'Koreksi', 'Elevasi Tetap', 'Jarak Kumulatif (m)'];
    }

    public function collection()
    {
        return $this->project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'hi', 'raw_elevation', 'correction', 'adjusted_elevation', 'cumulative_distance'])
            ->map(fn ($r) => [
                $r->sequence_no,
                $r->point_name,
                $r->hi,
                $r->raw_elevation,
                $r->correction,
                $r->adjusted_elevation,
                $r->cumulative_distance,
            ]);
    }
}

// ─── app/Exports/Sheets/KoreksiSheet.php ───────────────────────────────────

namespace App\Exports\Sheets;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class KoreksiSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public readonly Project $project) {}

    public function title(): string { return 'Koreksi'; }

    public function headings(): array
    {
        return ['No Urut', 'Titik', 'Elevasi Sementara', 'Koreksi', 'Elevasi Tetap'];
    }

    public function collection()
    {
        return $this->project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'raw_elevation', 'correction', 'adjusted_elevation'])
            ->map(fn ($r) => [
                $r->sequence_no,
                $r->point_name,
                $r->raw_elevation,
                $r->correction,
                $r->adjusted_elevation,
            ]);
    }
}

// ─── app/Exports/Sheets/RingkasanSheet.php ─────────────────────────────────

namespace App\Exports\Sheets;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RingkasanSheet implements FromArray, WithTitle
{
    public function __construct(public readonly Project $project) {}

    public function title(): string { return 'Ringkasan'; }

    public function array(): array
    {
        $p = $this->project;

        return [
            ['Proyek',              $p->name],
            ['Lokasi',              $p->location],
            ['Tanggal Survei',      $p->survey_date?->format('Y-m-d')],
            ['Benchmark',           $p->benchmark_name],
            ['Elevasi Benchmark',   $p->benchmark_elevation],
            ['Kelas Toleransi',     $p->tolerance_class],
            ['Metode Perataan',     $p->adjustment_method],
            [''],
            ['Kesalahan Penutup (fh)',  $p->closure_error],
            ['Toleransi yang Diizinkan', $p->allowed_tolerance],
            ['Jarak Total (km)',        $p->total_distance_km],
            ['Status',                  $p->status],
        ];
    }
}