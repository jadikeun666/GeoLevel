<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private readonly AdjustmentService $adjustmentService,
    ) {}

    public function index(Request $request): Response
    {
        $projects = $request->user()
            ->projects()
            ->latest()
            ->paginate(15);

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create', [
            'toleranceClasses'  => array_keys(config('geolevel.tolerance_classes')),
            'adjustmentMethods' => config('geolevel.adjustment_methods'),
            'defaults' => [
                'tolerance_class'   => config('geolevel.default_tolerance_class'),
                'adjustment_method' => config('geolevel.default_adjustment_method'),
            ],
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $request->user()->projects()->create($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil dibuat.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);

        $project->load([
            'readings'           => fn ($q) => $q->orderBy('sequence_no'),
            'computedElevations' => fn ($q) => $q->orderBy('sequence_no'),
        ]);

        return Inertia::render('Projects/Show', [
            'project'   => $project,
            'canExport' => $project->status === 'accepted',
        ]);
    }

    public function edit(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);

        return Inertia::render('Projects/Edit', [
            'project'           => $project,
            'toleranceClasses'  => array_keys(config('geolevel.tolerance_classes')),
            'adjustmentMethods' => config('geolevel.adjustment_methods'),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->update($request->validated());

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Proyek berhasil diperbarui.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Proyek berhasil dihapus.');
    }

    public function adjust(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $method = $request->validate([
            'method' => ['required', 'string', 'in:equal,bowditch,least_squares'],
        ])['method'];

        $this->adjustmentService->adjust($project, $method, $request->user()->id);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Adjustment berhasil diterapkan.');
    }

    public function resetAdjustment(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $this->adjustmentService->reset($project, $request->user()->id);

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Adjustment berhasil direset.');
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 403);
    }
}
