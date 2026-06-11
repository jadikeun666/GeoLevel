<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingRequest;
use App\Http\Requests\UpdateReadingRequest;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    /**
     * POST /projects/{project}/readings
     *
     * sequence_no di-auto-increment — tidak boleh di-input user.
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah created.
     *
     * PERUBAHAN: Ganti return type dari RedirectResponse → JsonResponse.
     * Test pakai postJson() yang set header Accept: application/json —
     * kalau controller return redirect (302), test akan gagal karena
     * mengharapkan status 201, bukan 302.
     */
    public function store(StoreReadingRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $nextSeq = ($project->readings()->max('sequence_no') ?? 0) + 1;

        $reading = $project->readings()->create(
            array_merge($request->validated(), ['sequence_no' => $nextSeq])
        );

        return response()->json(['data' => $reading], 201);
    }

    /**
     * PUT /projects/{project}/readings/{reading}
     *
     * sequence_no TIDAK boleh diubah setelah tersimpan — immutable by design.
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah updated.
     *
     * PERUBAHAN: Ganti return type dari RedirectResponse → JsonResponse (200).
     */
    public function update(UpdateReadingRequest $request, Project $project, Reading $reading): JsonResponse
    {
        $this->authorize('update', $project);

        abort_if($reading->project_id !== $project->id, 404);

        $reading->update($request->validated());

        return response()->json(['data' => $reading->fresh()], 200);
    }

    /**
     * DELETE /projects/{project}/readings/{reading}
     *
     * Engineering Rule #10: "Never hard-delete readings — preserve survey history permanently"
     * Delete hanya diizinkan jika status proyek 'draft' atau 'calculated',
     * dan hanya untuk reading terakhir (sequence_no tertinggi).
     *
     * PERUBAHAN: Ganti return type dari RedirectResponse → JsonResponse.
     * - Sukses: 204 No Content
     * - Gagal validasi bisnis: 422 dengan pesan error
     */
    public function destroy(Request $request, Project $project, Reading $reading): JsonResponse
    {
        $this->authorize('update', $project);

        abort_if($reading->project_id !== $project->id, 404);

        if (! in_array($project->status, ['draft', 'calculated'], true)) {
            return response()->json([
                'message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.',
            ], 422);
        }

        $lastSeq = $project->readings()->max('sequence_no');
        if ($reading->sequence_no !== $lastSeq) {
            return response()->json([
                'message' => 'Hanya bacaan terakhir yang dapat dihapus.',
            ], 422);
        }

        $reading->delete();

        return response()->json(null, 204);
    }
}