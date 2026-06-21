<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNetworkLegRequest;
use App\Models\NetworkLeg;
use App\Models\Project;
use App\Services\LeastSquaresAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * NetworkLegController
 *
 * CRUD untuk network_legs — jalur tambahan dalam jaring sipat datar
 * dengan redundant observations (lihat docs/formulas.md §"Loop Network
 * Least Squares" dan LeastSquaresAdjustmentService).
 *
 * Berbeda dari ReadingController: network legs OPSIONAL dan terpisah
 * dari readings biasa — proyek standar (traverse linear) tidak perlu
 * menyentuh endpoint ini sama sekali.
 */
class NetworkLegController extends Controller
{
    public function __construct(
        private readonly LeastSquaresAdjustmentService $leastSquaresService,
    ) {}

    /**
     * GET /projects/{project}/network-legs
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json([
            'legs'               => $project->networkLegs()->orderBy('id')->get(),
            'has_redundancy'     => $project->hasRedundantNetworkObservations(),
            'network_variance'      => $project->network_variance,
            'network_std_deviation' => $project->network_std_deviation,
            'network_degrees_of_freedom' => $project->network_degrees_of_freedom,
        ]);
    }

    /**
     * POST /projects/{project}/network-legs
     */
    public function store(StoreNetworkLegRequest $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        $leg = $project->networkLegs()->create($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['leg' => $leg], 201);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Jalur jaring berhasil ditambahkan.']);
    }

    /**
     * DELETE /projects/{project}/network-legs/{leg}
     */
    public function destroy(Request $request, Project $project, NetworkLeg $leg): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);
        abort_unless($leg->project_id === $project->id, 404);

        $leg->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Jalur dihapus.']);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Jalur jaring dihapus.']);
    }

    /**
     * POST /projects/{project}/network-legs/adjust
     *
     * Jalankan least squares network adjustment secara eksplisit
     * (terpisah dari AdjustmentService::applyToProject biasa, supaya
     * pesan error matriks — disconnected/no redundancy — sampai ke user
     * dengan jelas alih-alih ketelan generic 500).
     */
    public function adjust(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $project);

        if (!$project->hasRedundantNetworkObservations()) {
            $message = 'Jaring belum punya observasi berlebih (redundant) — tambahkan minimal satu jalur tambahan yang membentuk loop sebelum menjalankan perataan kuadrat terkecil.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('flash', ['type' => 'error', 'message' => $message]);
        }

        try {
            $result = $this->leastSquaresService->adjustProject($project, $request->user()->id);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return back()->with('flash', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['result' => $result]);
        }

        return back()->with('flash', [
            'type'    => 'success',
            'message' => "Perataan kuadrat terkecil selesai — std. deviasi: {$result['std_deviation']} m, derajat kebebasan: {$result['degrees_of_freedom']}.",
        ]);
    }
}