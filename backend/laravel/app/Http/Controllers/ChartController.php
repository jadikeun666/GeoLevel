<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\VisualizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChartController extends Controller
{
    public function __construct(private VisualizationService $viz) {}

    /**
     * GET /projects/{id}/chart/longsection
     */
    public function longSection(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'data'  => $this->viz->longSection($project),
            'title' => "Profil Memanjang — {$project->name}",
        ]);
    }

    /**
     * GET /projects/{id}/chart/crosssection
     * Optional query param: ?station=STA+0%2B000
     */
    public function crossSection(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $stations = $this->viz->crossSectionStations($project);

        if ($request->filled('station')) {
            $data = $this->viz->crossSection($project, $request->query('station'));
            if (! $data) {
                return response()->json(['error' => 'Stasiun tidak ditemukan'], 404);
            }
            return response()->json(['data' => $data, 'stations' => $stations]);
        }

        // Default: return first station if available
        if (empty($stations)) {
            return response()->json(['data' => null, 'stations' => []]);
        }

        $data = $this->viz->crossSection($project, $stations[0]);
        return response()->json(['data' => $data, 'stations' => $stations]);
    }
}