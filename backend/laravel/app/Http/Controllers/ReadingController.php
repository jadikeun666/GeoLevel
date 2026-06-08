<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReadingRequest;
use App\Http\Requests\UpdateReadingRequest;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReadingController extends Controller
{
    /**
     * POST /projects/{project}/readings
     *
     * sequence_no di-auto-increment — tidak boleh di-input user
     * karena "ORDER IS CRITICAL — never skip or reuse" (database.md).
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah created.
     */
    public function store(StoreReadingRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $nextSeq = ($project->readings()->max('sequence_no') ?? 0) + 1;

        $project->readings()->create(
            array_merge($request->validated(), ['sequence_no' => $nextSeq])
        );

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Bacaan disimpan. Perhitungan ulang dijadwalkan.',
        ]);
    }

    /**
     * PUT /projects/{project}/readings/{reading}
     *
     * Update hanya field yang diizinkan oleh UpdateReadingRequest.
     * sequence_no TIDAK boleh diubah setelah tersimpan — immutable by design.
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah updated.
     */
    public function update(UpdateReadingRequest $request, Project $project, Reading $reading): RedirectResponse
    {
        $this->authorize('update', $project);

        // Pastikan reading memang milik project ini
        abort_if($reading->project_id !== $project->id, 404);

        $reading->update($request->validated());

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Bacaan berhasil diperbarui.',
        ]);
    }

    /**
     * DELETE /projects/{project}/readings/{reading}
     *
     * Engineering Rule #10: "Never hard-delete readings — preserve survey history permanently"
     * (engineering-rules.md baris 10)
     *
     * Karena schema readings belum punya deleted_at (soft delete),
     * delete HANYA diizinkan jika:
     *   - Status proyek masih 'draft' atau 'calculated'
     *   - Reading adalah entri TERAKHIR (sequence_no tertinggi)
     *
     * Alasan pembatasan ke reading terakhir: menghapus di tengah akan
     * membuat sequence_no berlubang — melanggar "never skip or reuse".
     * Observer akan otomatis dispatch RecalculateSurveyJob setelah deleted.
     */
    public function destroy(Request $request, Project $project, Reading $reading): RedirectResponse
    {
        $this->authorize('update', $project);

        // Pastikan reading memang milik project ini
        abort_if($reading->project_id !== $project->id, 404);

        // Hanya boleh dihapus saat status draft atau calculated
        if (! in_array($project->status, ['draft', 'calculated'], true)) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Bacaan hanya dapat dihapus pada proyek berstatus draft atau calculated.',
            ]);
        }

        // Hanya boleh hapus reading terakhir — menjaga integritas sequence_no
        $lastSeq = $project->readings()->max('sequence_no');
        if ($reading->sequence_no !== $lastSeq) {
            return back()->with('flash', [
                'type'    => 'error',
                'message' => 'Hanya bacaan terakhir (sequence tertinggi) yang dapat dihapus untuk menjaga urutan data.',
            ]);
        }

        $reading->delete();

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Bacaan dihapus. Perhitungan ulang dijadwalkan.',
        ]);
    }
}