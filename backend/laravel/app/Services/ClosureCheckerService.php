<?php

namespace App\Services;

use App\Events\ClosureChecked;
use App\Models\ActivityLog;
use App\Models\Project;

class ClosureCheckerService
{
    // -----------------------------------------------------------------------
    // Pure computation helpers (formulas.md)
    //   fh = |ΣBS − ΣFS|
    //   r  = c × √(d_km)
    //   status = accepted (fh <= r) | rejected (fh > r)
    //
    // Precision: bcmath throughout. computeAllowedTolerance intentionally
    // returns higher-than-storage precision — rounding to NUMERIC(10,6)
    // happens at the DB layer / presentation, per engineering-rules.md.
    // -----------------------------------------------------------------------

    public static function computeClosureError(string $sumBs, string $sumFs): string
    {
        $diff = bcsub($sumBs, $sumFs, 6);

        return bccomp($diff, '0', 6) < 0 ? bcsub('0', $diff, 6) : $diff;
    }

    public static function computeAllowedTolerance(string $totalDistanceKm, string $toleranceClass): string
    {
        $c = (string) config("geolevel.tolerance_classes.{$toleranceClass}");

        $sqrtD = bcsqrt($totalDistanceKm, 10);

        return bcmul($c, $sqrtD, 10);
    }

    public static function determineStatus(string $closureError, string $allowedTolerance): string
    {
        return bccomp($closureError, $allowedTolerance, 10) <= 0 ? 'accepted' : 'rejected';
    }

    /**
     * @return array{closure_error: string, allowed_tolerance: string, status: string}
     */
    public static function check(string $sumBs, string $sumFs, string $totalDistanceKm, string $toleranceClass): array
    {
        $closureError     = self::computeClosureError($sumBs, $sumFs);
        $allowedTolerance = self::computeAllowedTolerance($totalDistanceKm, $toleranceClass);

        return [
            'closure_error'     => $closureError,
            'allowed_tolerance' => $allowedTolerance,
            'status'            => self::determineStatus($closureError, $allowedTolerance),
        ];
    }

    // -----------------------------------------------------------------------
    // Instance — triggered by RunClosureCheck listener on SurveyRecalculated.
    //
    // Closure values are already computed & persisted to `projects` by
    // LevelingCalculationService at this point. This method:
    //   1. Log activity closure_checked
    //   2. Dispatch ClosureChecked event for downstream listeners
    // -----------------------------------------------------------------------

    public function evaluate(int $projectId, int $userId): void
    {
        $project = Project::findOrFail($projectId);

        ActivityLog::create([
            'project_id'    => $projectId,
            'user_id'       => $userId,
            'activity_type' => ActivityLog::TYPE_CLOSURE_CHECKED,
            'description'   => "Closure check selesai untuk project #{$projectId}.",
            'metadata'      => [
                'closure_error'     => $project->closure_error,
                'allowed_tolerance' => $project->allowed_tolerance,
                'status'            => $project->status,
            ],
        ]);

        ClosureChecked::dispatch(
            $projectId,
            $userId,
            $project->status,
            (float) $project->closure_error,
            (float) $project->allowed_tolerance,
        );
    }
}