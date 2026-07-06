<?php

namespace App\Exports\Sheets;

use App\Models\Project;
use Illuminate\Support\Enumerable;
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

    public function collection(): Enumerable
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
