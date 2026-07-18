<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSurveyPointRequest;
use App\Models\Project;
use App\Models\SurveyPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SurveyPointController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data' => $project->surveyPoints,
        ]);
    }

    public function store(StoreSurveyPointRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $point = $project->surveyPoints()->updateOrCreate(
            ['point_name' => $request->validated('point_name')],
            $request->validated()
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $point], 201);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Koordinat titik berhasil disimpan.']);
    }

    public function update(StoreSurveyPointRequest $request, Project $project, SurveyPoint $point): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongsToProject($project, $point);

        $point->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['data' => $point]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Koordinat titik berhasil diperbarui.']);
    }

    public function destroy(Request $request, Project $project, SurveyPoint $point): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        $this->ensureBelongsToProject($project, $point);

        $point->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Koordinat titik berhasil dihapus.']);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Koordinat titik berhasil dihapus.']);
    }

    public function importBatch(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'points'                  => ['required', 'array', 'max:500'],
            'points.*.point_name'     => ['required', 'string', 'max:50'],
            'points.*.lat'            => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng'            => ['required', 'numeric', 'between:-180,180'],
            'points.*.point_type'     => ['nullable', 'in:BM,TP,IS,CP'],
            'points.*.elevation_ref'  => ['nullable', 'numeric'],
            'points.*.gps_accuracy_m' => ['nullable', 'numeric', 'min:0'],
        ]);

        $imported = 0;

        foreach ($validated['points'] as $p) {
            $project->surveyPoints()->updateOrCreate(
                ['point_name' => $p['point_name']],
                [
                    'lat'            => $p['lat'],
                    'lng'            => $p['lng'],
                    'point_type'     => $p['point_type'] ?? 'TP',
                    'elevation_ref'  => $p['elevation_ref'] ?? null,
                    'gps_accuracy_m' => $p['gps_accuracy_m'] ?? null,
                    'source'         => 'gpx',
                ]
            );
            $imported++;
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => "{$imported} titik berhasil diimpor.", 'imported' => $imported]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "{$imported} titik berhasil diimpor."]);
    }

    private function ensureBelongsToProject(Project $project, SurveyPoint $point): void
    {
        abort_unless($point->project_id === $project->id, 404);
    }
}
