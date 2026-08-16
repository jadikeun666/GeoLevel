<?php

namespace App\Http\Controllers;

use App\Exceptions\ExportNotAllowedException;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Services\StaticMapRenderService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * GET /projects/{id}/export/pdf
     */
    public function pdf(Request $request, Project $project, StaticMapRenderService $mapRenderer): Response
    {
        $this->authorize('view', $project);

        if ($project->status !== 'accepted') {
            abort(403, 'Export not allowed. Survey status must be accepted.');
        }

        $computedElevations = $project->computedElevations()->orderBy('sequence_no')->get();

        $mapMode = $request->query('map_mode') === 'satellite' ? 'satellite' : 'street';
        $mapImageBase64 = $this->renderMapImage($project, $mapRenderer, $mapMode);

        $pdf = Pdf::loadView('exports.field_book', [
            'project'            => $project,
            'rows' => $computedElevations,
            'mapImageBase64'     => $mapImageBase64,
        ]);

        ActivityLog::create([
            'project_id'    => $project->id,
            'user_id'       => $request->user()->id,
            'activity_type' => 'export_generated',
            'description'   => "PDF export generated for project {$project->id}",
            'metadata'      => ['type' => 'pdf'],
        ]);

        $filename = "project_{$project->id}_field_book.pdf";

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * GET /projects/{id}/export/excel
     */
    public function excel(Request $request, Project $project): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('view', $project);

        if ($project->status !== 'accepted') {
            abort(403, 'Export not allowed. Survey status must be accepted.');
        }

        ActivityLog::create([
            'project_id'    => $project->id,
            'user_id'       => $request->user()->id,
            'activity_type' => 'export_generated',
            'description'   => "Excel export generated for project {$project->id}",
            'metadata'      => ['type' => 'excel'],
        ]);

        $filename = "project_{$project->id}_data.xlsx";

        return Excel::download(
            new \App\Exports\SurveyExport($project),
            $filename
        );
    }

    /**
     * GET /projects/{id}/export/csv
     */
    public function csv(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        if ($project->status !== 'accepted') {
            abort(403, 'Export not allowed. Survey status must be accepted.');
        }

        $rows = $project->computedElevations()
            ->orderBy('sequence_no')
            ->get(['sequence_no', 'point_name', 'cumulative_distance', 'adjusted_elevation', 'correction']);

        $lines = $rows->map(fn ($r) => implode(',', [
            $r->sequence_no,
            $r->point_name,
            number_format((float) $r->cumulative_distance, 3, '.', ''),
            number_format((float) $r->adjusted_elevation, 4, '.', ''),
            number_format((float) $r->correction, 6, '.', ''),
        ]))->join("\n");

        ActivityLog::create([
            'project_id'    => $project->id,
            'user_id'       => $request->user()->id,
            'activity_type' => 'export_generated',
            'description'   => "CSV export generated for project {$project->id}",
            'metadata'      => ['type' => 'csv'],
        ]);

        $filename = "project_{$project->id}_data.csv";

        return response($lines, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Render peta statis (basemap + marker + polyline + network legs) untuk
     * disisipkan ke field book PDF sebagai <img> base64. Mengembalikan null
     * kalau proyek tidak punya survey_points sama sekali -- Blade wajib
     * skip section peta tanpa error kalau ini null.
     */
    private function renderMapImage(Project $project, StaticMapRenderService $mapRenderer, string $mapMode): ?string
    {
        $surveyPoints = $project->surveyPoints()->get(['point_name', 'lat', 'lng', 'point_type']);

        if ($surveyPoints->isEmpty()) {
            return null;
        }

        $points = $surveyPoints->map(fn ($p) => [
            'point_name' => $p->point_name,
            'lat'        => (float) $p->lat,
            'lng'        => (float) $p->lng,
            'point_type' => $p->point_type,
        ])->all();

        $elevations = $project->computedElevations()
            ->get(['point_name', 'sequence_no'])
            ->map(fn ($e) => [
                'point_name'  => $e->point_name,
                'sequence_no' => $e->sequence_no,
            ])->all();

        $networkLegs = $project->networkLegs()
            ->get(['from_point', 'to_point'])
            ->map(fn ($l) => [
                'from_point' => $l->from_point,
                'to_point'   => $l->to_point,
            ])->all();

        $layout = $mapRenderer->computeLayout($points);

        $canvas = $mapRenderer->renderBaseMap($layout, $mapMode);
        $mapRenderer->drawNetworkLegs($canvas, $points, $networkLegs, $layout, $project->status);
        $mapRenderer->drawRoute($canvas, $points, $elevations, $layout);

        foreach ($points as $point) {
            $mapRenderer->drawMarker($canvas, $point['lat'], $point['lng'], $point['point_type'], $layout);
        }

        ob_start();
        imagepng($canvas);
        $imageData = ob_get_clean();

        return 'data:image/png;base64,' . base64_encode($imageData);
    }
}
