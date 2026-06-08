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
    // Instance methods — dipakai dari controller/job (butuh DB + Project)
    // -----------------------------------------------------------------------

    public function applyToProject(Project $project, string $method, int $userId): void
    {
        $run = function () use ($project, $method, $userId) {
            $rows = ComputedElevation::where('project_id', $project->id)
                ->orderBy('sequence_no')
                ->get();

            // Hitung ulang fh dari readings — jangan percaya project->closure_error
            // karena bisa null saat test atau belum di-update
            $readings = $project->readings()->orderBy('sequence_no')->get();
            $sumBS = '0';
            $sumFS = '0';
            $totalDistanceM = '0';
            foreach ($readings as $r) {
                $bt = (string) $r->bt;
                $dist = $r->distance_m !== null
                    ? (string) $r->distance_m
                    : bcmul(bcsub((string)$r->ba, (string)$r->bb, self::SCALE), '100', self::SCALE);
                if ($r->reading_type === 'BS') {
                    $sumBS = bcadd($sumBS, $bt, self::SCALE);
                } elseif ($r->reading_type === 'FS') {
                    $sumFS = bcadd($sumFS, $bt, self::SCALE);
                }
                $totalDistanceM = bcadd($totalDistanceM, $dist, self::SCALE);
            }
            $fhSigned = bcsub($sumBS, $sumFS, self::SCALE);
            $fh = bccomp($fhSigned, '0', self::SCALE) < 0
                  ? bcsub('0', $fhSigned, self::SCALE)
                  : $fhSigned;

            $nFS       = $rows->where('reading_type', 'FS')->count();
            $unit      = self::equalCorrectionPerPoint($fh, $nFS);
            $fsCounter = 0;

            foreach ($rows as $row) {
                if ($row->reading_type === 'BS') {
                    $row->forceFill([
                        'correction'         => '0.000000',
                        'adjusted_elevation' => number_format((float) $row->raw_elevation, 4, '.', ''),
                    ])->saveQuietly();
                    continue;
                }

                if ($row->reading_type === 'FS') {
                    $fsCounter++;
                }

                $correction = match ($method) {
                    'bowditch' => self::bowditchCorrection(
                        $fh,
                        (string) $row->cumulative_distance,
                        $totalDistanceM
                    ),
                    default => self::equalCorrectionCumulative($unit, $fsCounter),
                };

                $row->forceFill([
                    'correction'         => number_format((float) $correction, 6, '.', ''),
                    'adjusted_elevation' => number_format(
                        (float) self::applyCorrection((string) $row->raw_elevation, $correction),
                        4, '.', ''
                    ),
                ])->saveQuietly();
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
            ComputedElevation::where('project_id', $project->id)
                ->each(function (ComputedElevation $row) {
                    $row->correction         = '0';
                    $row->adjusted_elevation = $row->raw_elevation;
                    $row->save();
                });

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
    // Public static helpers — pure math, testable in isolation (no DB)
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
                'bowditch' => self::bowditchCorrection(
                    $fh,
                    (string) $row['cumulative_distance'],
                    $totalDistance
                ),
                default => self::equalCorrectionCumulative($unitCorrection, $fsCounter),
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