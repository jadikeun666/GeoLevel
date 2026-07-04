<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingRequest;
use App\Http\Requests\UpdateReadingRequest;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Delete hanya diizinkan jika status proyek 'draft' atau 'calculated'.
     *
     * Setelah soft delete, sequence_no semua reading yang tersisa di-renumber
     * ulang secara berurutan (1, 2, 3, ...) agar tidak ada gap.
     * Renumber dilakukan dalam satu transaction bersama delete agar konsisten.
     *
     * Observer ReadingObserver akan dispatch RecalculateSurveyJob setelah
     * delete selesai — tidak perlu dispatch manual di sini.
     */
    public function destroy(Request $request, Project $project, Reading $reading): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        abort_if($reading->project_id !== $project->id, 404);

        if ($project->status === 'accepted') {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.',
                ], 422);
            }
            return back()->with('flash', ['type' => 'error', 'message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.']);
        }

        DB::transaction(function () use ($reading, $project) {
            // 1. Soft delete reading ini
            $reading->delete();

            // 2. Ambil semua reading yang tersisa, urut by sequence_no lama
            //    withTrashed() dikecualikan — kita hanya renumber yang aktif
            $remaining = $project->readings()
                ->orderBy('sequence_no')
                ->get(['id']);

            // 3. Renumber berurutan mulai 1 — tanpa trigger observer
            //    (gunakan DB::table agar tidak fire Eloquent events yang
            //    akan dispatch RecalculateSurveyJob berkali-kali)
            foreach ($remaining as $index => $r) {
                DB::table('readings')
                    ->where('id', $r->id)
                    ->update(['sequence_no' => $index + 1]);
            }

            // Observer pada $reading->delete() di atas sudah men-dispatch
            // RecalculateSurveyJob — job itu akan berjalan setelah transaction
            // commit, sehingga akan melihat sequence_no yang sudah direnumber.
        });

        if ($request->expectsJson()) {
            return response()->json(null, 204);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Bacaan berhasil dihapus.']);
    }
}