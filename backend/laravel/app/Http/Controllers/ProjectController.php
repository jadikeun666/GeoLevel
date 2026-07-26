<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Jobs\RecalculateSurveyJob;
use App\Models\Project;
use App\Services\AdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly AdjustmentService $adjuster,
    ) {}

    /**
     * GET /projects
     * Daftar semua proyek milik user yang sedang login.
     */
    public function index(Request $request): Response
    {
        $projects = $request->user()
            ->projects()
            ->orderByDesc('survey_date')
            ->get([
                'id', 'name', 'location', 'survey_date',
                'benchmark_name', 'benchmark_elevation',
                'tolerance_class', 'adjustment_method',
                'closure_error', 'allowed_tolerance',
                'total_distance_km', 'status',
            ]);

        // Titik rata-rata koordinat per proyek, untuk mini-map overview.
        // Proyek tanpa survey_points tidak muncul di sini (whereHas).
        $mapPoints = $request->user()
            ->projects()
            ->whereHas('surveyPoints')
            ->with(['surveyPoints' => fn ($q) => $q->select('project_id', 'lat', 'lng')])
            ->get(['id', 'name', 'status'])
            ->map(fn ($p) => [
                'project_id'   => $p->id,
                'project_name' => $p->name,
                'status'       => $p->status,
                'center_lat'   => (float) $p->surveyPoints->avg('lat'),
                'center_lng'   => (float) $p->surveyPoints->avg('lng'),
                'point_count'  => $p->surveyPoints->count(),
            ])
            ->values();

        return Inertia::render('Projects/Index', compact('projects', 'mapPoints'));
    }

    /**
     * POST /projects
     * Buat proyek baru. Validasi via StoreProjectRequest.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project->id)
            ->with('flash', ['type' => 'success', 'message' => 'Proyek berhasil dibuat.']);
    }

    /**
     * GET /projects/{project}
     * Halaman detail: readings, elevasi, activity log, network legs.
     *
     * PERUBAHAN: Tambah $networkLegs dari relasi networkLegs() dan
     * sertakan ke response JSON maupun Inertia::render().
     */
    public function show(Request $request, Project $project): Response|JsonResponse
    {
        $this->authorize('view', $project);

        $readings     = $project->readings()->orderBy('sequence_no')->get();
        $elevations   = $project->computedElevations()->orderBy('sequence_no')->get();
        $activityLogs = $project->activityLogs()->latest()->take(50)->get();
        $networkLegs  = $project->networkLegs()->orderBy('id')->get();
        $surveyPoints = $project->surveyPoints()->orderBy('point_name')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'project'      => $project,
                'readings'     => $readings,
                'elevations'   => $elevations,
                'activityLogs' => $activityLogs,
                'networkLegs'  => $networkLegs,
                'surveyPoints' => $surveyPoints,
            ]);
        }

        return Inertia::render('Projects/Show', [
            'project'      => $project,
            'readings'     => $readings,
            'elevations'   => $elevations,
            'activityLogs' => $activityLogs,
            'networkLegs'  => $networkLegs,
            'surveyPoints' => $surveyPoints,
        ]);
    }

    /**
     * PUT /projects/{project}
     * Update metadata proyek (nama, lokasi, toleransi, dll).
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $project->update($request->validated());

        return back()->with('flash', ['type' => 'success', 'message' => 'Proyek berhasil diperbarui.']);
    }

    /**
     * DELETE /projects/{project}
     * Hapus proyek beserta seluruh data terkait (cascade di DB).
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')
            ->with('flash', ['type' => 'success', 'message' => 'Proyek berhasil dihapus.']);
    }

    /**
     * POST /projects/{project}/calculate
     * Picu ulang perhitungan elevasi secara manual.
     */
    public function calculate(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        RecalculateSurveyJob::dispatch($project->id);

        return back()->with('flash', ['type' => 'info', 'message' => 'Perhitungan ulang dijadwalkan.']);
    }

    /**
     * POST /projects/{project}/adjust
     * Terapkan metode perataan (equal / bowditch / reset).
     */
    public function adjust(AdjustProjectRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $method = $request->validated('method');

        // 'reset' ditangani terpisah — bukan applyToProject()
        if ($method === 'reset') {
            $this->adjuster->reset(
                project: $project,
                userId:  $request->user()->id,
            );
        } else {
            $this->adjuster->applyToProject(
                project: $project,
                method:  $method,
                userId:  $request->user()->id,
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Perataan berhasil diterapkan.']);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Perataan berhasil diterapkan.']);
    }

    /**
     * POST /projects/{project}/adjust/reset
     * Reset semua correction = 0, adjusted_elevation = raw_elevation.
     */
    public function resetAdjustment(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $this->adjuster->reset(
            project: $project,
            userId:  $request->user()->id,
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Koreksi berhasil direset ke 0.']);
        }

        return back()->with('flash', ['type' => 'info', 'message' => 'Koreksi berhasil direset ke 0.']);
    }
}