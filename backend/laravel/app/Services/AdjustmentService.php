<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ComputedElevation;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class AdjustmentService
{
    private const SCALE = 10;

    // -----------------------------------------------------------------------
    // Instance methods
    // -----------------------------------------------------------------------

    public function applyToProject(Project $project, string $method, int $userId): void
    {
        $run = function () use ($project, $method, $userId) {
            $rows = ComputedElevation::where('computed_elevations.project_id', $project->id)
                ->join('readings', function ($join) {
                    $join->on('readings.project_id', '=', 'computed_elevations.project_id')
                         ->on('readings.sequence_no', '=', 'computed_elevations.sequence_no');
                })
                ->select('computed_elevations.*', 'readings.reading_type as reading_type')
                ->orderBy('computed_elevations.sequence_no')
                ->get();

            $fh             = (string) $project->closure_error;
            $totalDistanceM = bcmul((string) $project->total_distance_km, '1000', self::SCALE);

            $nFS       = $rows->where('reading_type', 'FS')->count();
            $unit      = self::equalCorrectionPerPoint($fh, $nFS);
            $fsCounter = 0;

            $updates = [];
            foreach ($rows as $row) {
                if ($row->reading_type === 'BS') {
                    $updates[] = [
                        'id'                 => $row->id,
                        'correction'         => '0.000000',
                        'adjusted_elevation' => number_format((float) $row->raw_elevation, 6, '.', ''),
                    ];
                    continue;
                }

                if ($row->reading_type === 'FS') {
                    $fsCounter++;
                }

                $correction = match ($method) {
                    'bowditch'     => self::bowditchCorrection(
                                         $fh,
                                         (string) $row->cumulative_distance,
                                         $totalDistanceM
                                     ),
                    'least_squares' => self::leastSquaresCorrection(
                                         $fh,
                                         (string) $row->cumulative_distance,
                                         $totalDistanceM
                                     ),
                    default        => self::equalCorrectionCumulative($unit, $fsCounter),
                };

                $updates[] = [
                    'id'                 => $row->id,
                    'correction'         => number_format((float) $correction, 6, '.', ''),
                    'adjusted_elevation' => number_format(
                        (float) self::applyCorrection((string) $row->raw_elevation, $correction),
                        6, '.', ''
                    ),
                ];
            }

            foreach ($updates as $upd) {
                DB::table('computed_elevations')
                    ->where('id', $upd['id'])
                    ->update([
                        'correction'         => $upd['correction'],
                        'adjusted_elevation' => $upd['adjusted_elevation'],
                        'updated_at'         => now(),
                    ]);
            }

            ActivityLog::create([
                'project_id'    => $project->id,
                'user_id'       => $userId,
                'activity_type' => ActivityLog::TYPE_ADJUSTMENT_APPLIED,
                'description'   => "Adjustment ({$method}) applied to project #{$project->id}",
                'metadata'      => ['method' => $method],
            ]);
        };

        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    public function reset(Project $project, int $userId): void
    {
        $run = function () use ($project, $userId) {
            DB::table('computed_elevations')
                ->where('project_id', $project->id)
                ->update([
                    'correction'         => '0.000000',
                    'adjusted_elevation' => DB::raw('raw_elevation'),
                    'updated_at'         => now(),
                ]);

            ActivityLog::create([
                'project_id'    => $project->id,
                'user_id'       => $userId,
                'activity_type' => ActivityLog::TYPE_ADJUSTMENT_RESET,
                'description'   => "Adjustment reset for project #{$project->id}",
                'metadata'      => [],
            ]);
        };

        DB::transactionLevel() > 0 ? $run() : DB::transaction($run);
    }

    // -----------------------------------------------------------------------
    // Public static helpers — pure math, testable in isolation
    // -----------------------------------------------------------------------

    public static function equalCorrectionPerPoint(string $fh, int $n): string
    {
        if ($n === 0) {
            return '0';
        }
        return bcsub('0', bcdiv($fh, (string) $n, self::SCALE), self::SCALE);
    }

    public static function equalCorrectionCumulative(string $unitCorrection, int $index): string
    {
        return bcmul($unitCorrection, (string) $index, self::SCALE);
    }

    public static function bowditchCorrection(
        string $fh,
        string $cumulativeDistance,
        string $totalDistance
    ): string {
        if (bccomp($totalDistance, '0', self::SCALE) === 0) {
            return '0';
        }
        $ratio = bcdiv($cumulativeDistance, $totalDistance, self::SCALE);
        $corr  = bcmul($fh, $ratio, self::SCALE);
        return bcsub('0', $corr, self::SCALE);
    }

    /**
     * Least Squares correction for open differential leveling traverse.
     *
     * For a single open traverse, the least squares solution with weights
     * proportional to 1/distance reduces to distance-proportional correction
     * (identical to Bowditch in formula, but derived via normal equations).
     *
     * Weight of each leg i: w_i = 1 / d_i
     * Normal equation: correction_i = -fh * (d_i / Σd)
     *
     * This matches Bowditch for uniform weight distribution. The distinction
     * becomes meaningful for loop networks with redundant observations (future).
     *
     * Reference: SNI 19-6988-2004 §6.3; Mikhail & Gracie, "Introduction to
     * Modern Photogrammetry", least squares leveling adjustment.
     */
    public static function leastSquaresCorrection(
        string $fh,
        string $cumulativeDistance,
        string $totalDistance
    ): string {
        if (bccomp($totalDistance, '0', self::SCALE) === 0) {
            return '0';
        }
        // Normal equation solution: v_i = -fh * (d_i / Σd)
        $ratio = bcdiv($cumulativeDistance, $totalDistance, self::SCALE);
        $corr  = bcmul($fh, $ratio, self::SCALE);
        return bcsub('0', $corr, self::SCALE);
    }

    public static function applyCorrection(string $rawElevation, string $correction): string
    {
        if (bccomp($correction, '0', self::SCALE) === 0) {
            return $rawElevation;
        }
        return bcadd($rawElevation, $correction, self::SCALE);
    }

    public static function adjust(array $computedElevations, string $fh, string $method): array
    {
        $nFS = count(array_filter(
            $computedElevations,
            fn ($r) => $r['reading_type'] === 'FS'
        ));

        $totalDistance = '0';
        foreach ($computedElevations as $row) {
            if (isset($row['cumulative_distance'])) {
                $totalDistance = (string) $row['cumulative_distance'];
            }
        }

        $unitCorrection = self::equalCorrectionPerPoint($fh, $nFS);
        $fsCounter      = 0;
        $results        = [];

        foreach ($computedElevations as $row) {
            $out = $row;

            if ($row['reading_type'] === 'BS') {
                $out['correction']         = '0';
                $out['adjusted_elevation'] = $row['raw_elevation'];
                $results[] = $out;
                continue;
            }

            if ($row['reading_type'] === 'FS') {
                $fsCounter++;
            }

            $correction = match ($method) {
                'bowditch'      => self::bowditchCorrection(
                                       $fh,
                                       (string) $row['cumulative_distance'],
                                       $totalDistance
                                   ),
                'least_squares' => self::leastSquaresCorrection(
                                       $fh,
                                       (string) $row['cumulative_distance'],
                                       $totalDistance
                                   ),
                default         => self::equalCorrectionCumulative($unitCorrection, $fsCounter),
            };

            $out['correction']         = $correction;
            $out['adjusted_elevation'] = self::applyCorrection(
                (string) $row['raw_elevation'],
                $correction
            );
            $results[] = $out;
        }

        return $results;
    }
}
