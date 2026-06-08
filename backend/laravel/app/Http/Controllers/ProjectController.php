<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdjustProjectRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Jobs\RecalculateSurveyJob;
use App\Models\Project;
use App\Services\AdjustmentService;
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

        return Inertia::render('Projects/Index', compact('projects'));
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
            ->with('flash', [
                'type'    => 'success',
                'message' => 'Proyek berhasil dibuat.',
            ]);
    }

    /**
     * GET /projects/{project}
     * Halaman detail: readings, elevasi, activity log.
     * Chart dimuat lazy via axios dari ChartController.
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $readings     = $project->readings()
            ->orderBy('sequence_no')
            ->get();

        $elevations   = $project->computedElevations()
            ->orderBy('sequence_no')
            ->get();

        $activityLogs = $project->activityLogs()
            ->latest()
            ->take(50)
            ->get();

        return Inertia::render('Projects/Show', [
            'project'      => $project,
            'readings'     => $readings,
            'elevations'   => $elevations,
            'activityLogs' => $activityLogs,
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

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Proyek berhasil diperbarui.',
        ]);
    }

    /**
     * DELETE /projects/{project}
     * Hapus proyek beserta seluruh data terkait (cascade di DB).
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('flash', [
                'type'    => 'success',
                'message' => 'Proyek berhasil dihapus.',
            ]);
    }

    /**
     * POST /projects/{project}/calculate
     * Picu ulang perhitungan elevasi secara manual.
     * ReadingObserver sudah otomatis dispatch saat reading berubah —
     * ini untuk trigger manual dari UI.
     */
    public function calculate(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        RecalculateSurveyJob::dispatch($project->id, $request->user()->id);

        return back()->with('flash', [
            'type'    => 'info',
            'message' => 'Perhitungan ulang dijadwalkan.',
        ]);
    }

    /**
     * POST /projects/{project}/adjust
     * Terapkan metode perataan (equal / bowditch / least_squares).
     * Hanya boleh jika status = calculated atau accepted.
     */
    public function adjust(AdjustProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->adjuster->apply(
            project: $project,
            method:  $request->validated('method'),
            userId:  $request->user()->id,
        );

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Perataan berhasil diterapkan.',
        ]);
    }

    /**
     * POST /projects/{project}/adjust/reset
     * Reset semua correction = 0, adjusted_elevation = raw_elevation.
     */
    public function resetAdjustment(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $this->adjuster->reset(
            project: $project,
            userId:  $request->user()->id,
        );

        return back()->with('flash', [
            'type'    => 'info',
            'message' => 'Koreksi berhasil direset ke 0.',
        ]);
    }
}