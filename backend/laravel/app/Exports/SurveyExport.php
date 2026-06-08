<?php

namespace App\Exports;

use App\Models\Project;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SurveyExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(public readonly Project $project) {}

    public function sheets(): array
    {
        return [
            new Sheets\RawReadingsSheet($this->project),
            new Sheets\ElevasiSheet($this->project),
            new Sheets\KoreksiSheet($this->project),
            new Sheets\RingkasanSheet($this->project),
        ];
    }
}