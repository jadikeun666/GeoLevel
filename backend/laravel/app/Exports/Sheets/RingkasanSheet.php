<?php

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
            ['Proyek',               $p->name],
            ['Lokasi',               $p->location],
            ['Tanggal Survei',       $p->survey_date?->format('Y-m-d')],
            ['Benchmark',            $p->benchmark_name],
            ['Elevasi Benchmark',    $p->benchmark_elevation],
            ['Kelas Toleransi',      $p->tolerance_class],
            ['Metode Perataan',      $p->adjustment_method],
            [''],
            ['Kesalahan Penutup (fh)',    $p->closure_error],
            ['Toleransi yang Diizinkan',  $p->allowed_tolerance],
            ['Jarak Total (km)',           $p->total_distance_km],
            ['Status',                     $p->status],
        ];
    }
}