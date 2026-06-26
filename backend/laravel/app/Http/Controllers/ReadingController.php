<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingRequest;
use App\Http\Requests\UpdateReadingRequest;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    /**
     * POST /projects/{project}/readings
     *
     * sequence_no di-auto-increment — tidak boleh di-input user.
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah created.
     *
     * Returns redirect for browser requests, JSON for API/JSON requests.
     */
    public function store(StoreReadingRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $nextSeq = ($project->readings()->max('sequence_no') ?? 0) + 1;

        $reading = $project->readings()->create(
            array_merge($request->validated(), ['sequence_no' => $nextSeq])
        );

        if ($request->expectsJson()) {
            return response()->json(['data' => $reading], 201);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Bacaan berhasil ditambahkan.']);
    }

    /**
     * PUT /projects/{project}/readings/{reading}
     *
     * sequence_no TIDAK boleh diubah setelah tersimpan — immutable by design.
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah updated.
     */
    public function update(UpdateReadingRequest $request, Project $project, Reading $reading): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        abort_if($reading->project_id !== $project->id, 404);

        $reading->update($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['data' => $reading->fresh()], 200);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Bacaan berhasil diperbarui.']);
    }

    /**
     * DELETE /projects/{project}/readings/{reading}
     *
     * Engineering Rule #10: "Never hard-delete readings — preserve survey history permanently"
     * Delete hanya diizinkan jika status proyek 'draft' atau 'calculated',
     * dan hanya untuk reading terakhir (sequence_no tertinggi).
     */
    public function destroy(Request $request, Project $project, Reading $reading): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        abort_if($reading->project_id !== $project->id, 404);

        if (! in_array($project->status, ['draft', 'calculated'], true)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.',
                ], 422);
            }
            return back()->withErrors(['message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.']);
        }

        $lastSeq = $project->readings()->max('sequence_no');
        if ($reading->sequence_no !== $lastSeq) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Hanya bacaan terakhir yang dapat dihapus.',
                ], 422);
            }
            return back()->withErrors(['message' => 'Hanya bacaan terakhir yang dapat dihapus.']);
        }

        $reading->delete();

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Bacaan berhasil dihapus.']);
    }
}