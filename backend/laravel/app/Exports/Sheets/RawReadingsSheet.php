<?php
// File: app/Exports/Sheets/RawReadingsSheet.php

namespace App\Exports\Sheets;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class RawReadingsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(public readonly Project $project) {}

    public function title(): string { return 'Bacaan Mentah'; }

    public function headings(): array
    {
        return ['No Urut', 'Titik', 'Tipe', 'BA', 'BT', 'BB', 'Jarak (m)', 'Catatan'];
    }

    public function collection()
    {
        return $this->project->readings()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'reading_type', 'ba', 'bt', 'bb', 'distance_m', 'notes'])
            ->map(fn ($r) => [
                $r->sequence_no,
                $r->point_name,
                $r->reading_type,
                $r->ba,
                $r->bt,
                $r->bb,
                $r->distance_m,
                $r->notes,
            ]);
    }
}