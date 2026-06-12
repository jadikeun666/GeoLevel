<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(private ExportService $exports) {}

    /**
     * GET /projects/{id}/export/pdf
     */
    public function pdf(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return $this->dispatch(fn () => $this->exports->exportPdf($project, $request->user()->id));
    }

    /**
     * GET /projects/{id}/export/excel
     */
    public function excel(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return $this->dispatch(fn () => $this->exports->exportExcel($project, $request->user()->id));
    }

    /**
     * GET /projects/{id}/export/csv
     */
    public function csv(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return $this->dispatch(fn () => $this->exports->exportCsv($project, $request->user()->id));
    }

    private function dispatch(\Closure $fn): JsonResponse
    {
        try {
            $result = $fn();

            // CSV job returns a URL for immediate download
            if (is_array($result) && isset($result['url'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Export generated.',
                    'data'    => ['url' => $result['url']],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Export queued. You will be notified when ready.',
            ]);
        } catch (\App\Exceptions\ExportNotAllowedException $e) {
            // Business rule: export only allowed when status = accepted
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}