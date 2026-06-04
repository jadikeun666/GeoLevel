<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingRequest;
use App\Http\Requests\UpdateReadingRequest;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReadingController extends Controller
{
    public function index(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);

        $readings = $project->readings()
            ->orderBy('sequence_no')
            ->get();

        return Inertia::render('Readings/Index', [
            'project'  => $project,
            'readings' => $readings,
        ]);
    }

    public function create(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);

        $nextSeq = $project->readings()->max('sequence_no') + 1;

        return Inertia::render('Readings/Create', [
            'project' => $project,
            'nextSeq' => $nextSeq,
        ]);
    }

    public function store(StoreReadingRequest $request, Project $project): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeProject($request, $project);

        // sequence_no harus unik per proyek
        $exists = $project->readings()
            ->where('sequence_no', $request->sequence_no)
            ->exists();

        if ($exists) {
            return back()->withErrors(['sequence_no' => 'Sequence number sudah digunakan.']);
        }

        $project->readings()->create($request->validated());

        // Observer akan dispatch RecalculateSurveyJob otomatis

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Data bacaan berhasil disimpan.');
    }

    public function edit(Request $request, Project $project, Reading $reading): Response
    {
        $this->authorizeProject($request, $project);
        $this->authorizeReading($project, $reading);

        return Inertia::render('Readings/Edit', [
            'project' => $project,
            'reading' => $reading,
        ]);
    }

    public function update(UpdateReadingRequest $request, Project $project, Reading $reading): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeReading($project, $reading);

        $reading->update($request->validated());

        // Observer akan dispatch RecalculateSurveyJob otomatis

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Data bacaan berhasil diperbarui.');
    }

    public function destroy(Request $request, Project $project, Reading $reading): \Illuminate\Http\RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeReading($project, $reading);

        // Engineering rule: never hard-delete — soft delete tidak dipakai
        // tapi data tetap preserve dengan cara memindahkan ke arsip jika perlu.
        // Untuk sekarang: hard delete diizinkan hanya jika project masih draft.
        abort_unless($project->isDraft(), 403, 'Readings hanya bisa dihapus saat proyek masih draft.');

        $reading->delete();

        // Observer akan dispatch RecalculateSurveyJob otomatis

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Data bacaan berhasil dihapus.');
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless($project->user_id === $request->user()->id, 403);
    }

    private function authorizeReading(Project $project, Reading $reading): void
    {
        abort_unless($reading->project_id === $project->id, 404);
    }
}
