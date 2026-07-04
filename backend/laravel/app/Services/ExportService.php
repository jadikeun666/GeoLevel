<?php

namespace App\Services;

use App\Events\ExportGenerated;
use App\Exceptions\ExportNotAllowedException;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ExportService
{
    public function exportPdf(Project $project): string
    {
        if ($project->status !== 'accepted') {
            throw new ExportNotAllowedException();
        }

        $computedElevations = $project->computedElevations()->orderBy('sequence_no')->get();

        $pdf = Pdf::loadView('exports.field_book', [
            'project'           => $project,
            'computedElevations' => $computedElevations,
        ]);

        $path = "exports/{$project->id}/field_book.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        event(new ExportGenerated($project->id, Auth::id(), 'pdf', $path));

        return $path;
    }

    public function exportExcel(Project $project): string
    {
        if ($project->status !== 'accepted') {
            throw new ExportNotAllowedException();
        }

        $path = "exports/{$project->id}/data.xlsx";

        Excel::store(
            new \App\Exports\ProjectExport($project),
            $path,
            'local'
        );

        event(new ExportGenerated($project->id, Auth::id(), 'excel', $path));

        return $path;
    }

    public function exportCsv(Project $project): string
    {
        if ($project->status !== 'accepted') {
            throw new ExportNotAllowedException();
        }

        $rows = $project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'cumulative_distance', 'adjusted_elevation', 'correction']);

        $lines = $rows->map(fn ($r) =>
            implode(',', [
                $r->sequence_no,
                $r->point_name,
                $r->cumulative_distance,
                $r->adjusted_elevation,
                $r->correction,
            ])
        )->join("\n");

        $path = "exports/{$project->id}/data.csv";
        Storage::disk('local')->put($path, $lines);

        event(new ExportGenerated($project->id, Auth::id(), 'csv', $path));

        return $path;
    }
}
