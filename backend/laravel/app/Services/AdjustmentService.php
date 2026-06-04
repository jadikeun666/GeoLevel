<?php

namespace App\Services;

use App\Events\AdjustmentApplied;
use App\Models\ActivityLog;
use App\Models\ComputedElevation;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustmentService
{
    private const SCALE = 10;

    private const SUPPORTED_METHODS = ['equal', 'bowditch', 'least_squares'];

    public function adjust(Project $project, string $method, int $userId): void
    {
        if (! in_array($method, self::SUPPORTED_METHODS, true)) {
            throw new InvalidArgumentException("Metode adjustment tidak valid: {$method}");
        }

        if ($method === 'least_squares') {
            throw new InvalidArgumentException("Metode least_squares belum diimplementasikan.");
        }

        DB::transaction(function () use ($project, $method, $userId) {
            $rows = ComputedElevation::where('project_id', $project->id)
                ->orderBy('sequence_no')
                ->get();

            if ($rows->isEmpty()) {
                throw new InvalidArgumentException("Tidak ada computed elevations untuk project #{$project->id}.");
            }

            $fh        = (string) $project->closure_error;
            $totalD    = (string) ($rows->last()->cumulative_distance ?? '0');
            $fhSigned  = bccomp($fh, '0', self::SCALE) >= 0 ? $fh : bcsub('0', $fh, self::SCALE);
            $negative  = bccomp(
                bcsub((string) $project->closure_error, '0', self::SCALE),
                '0',
                self::SCALE
            ) >= 0;

            $fsRows   = $rows->where('reading_type', '!=', 'BS')->values();
            $nFS      = $fsRows->count();
            $unitCorr = $nFS > 0 ? bcdiv($fhSigned, (string) $nFS, self::SCALE) : '0';
            $fsIndex  = 0;

            foreach ($rows as $row) {
                if ($row->hi !== null) {
                    // BS row — reset correction
                    $row->correction         = '0.000000';
                    $row->adjusted_elevation = $row->raw_elevation;
                } else {
                    $fsIndex++;

                    $correction = match ($method) {
                        'bowditch' => $this->bowditchCorrection(
                            $fhSigned, $negative,
                            (string) $row->cumulative_distance,
                            $totalD
                        ),
                        default => $this->equalCorrection($unitCorr, $negative, $fsIndex),
                    };

                    $row->correction         = number_format((float) $correction, 6, '.', '');
                    $row->adjusted_elevation = number_format(
                        (float) bcadd((string) $row->raw_elevation, $correction, self::SCALE),
                        4, '.', ''
                    );
                }

                $row->save();
            }

            $project->update(['adjustment_method' => $method]);

            ActivityLog::create([
                'project_id'    => $project->id,
                'user_id'       => $userId,
                'activity_type' => ActivityLog::TYPE_ADJUSTMENT_APPLIED,
                'description'   => "Adjustment '{$method}' diterapkan pada project #{$project->id}.",
                'metadata'      => [
                    'method'        => $method,
                    'closure_error' => $project->closure_error,
                    'point_count'   => $nFS,
                ],
            ]);
        });

        AdjustmentApplied::dispatch($project->id, $userId, $method);
    }

    public function reset(Project $project, int $userId): void
    {
        DB::transaction(function () use ($project, $userId) {
            ComputedElevation::where('project_id', $project->id)
                ->update([
                    'correction'        => '0.000000',
                    'adjusted_elevation' => DB::raw('raw_elevation'),
                ]);

            ActivityLog::create([
                'project_id'    => $project->id,
                'user_id'       => $userId,
                'activity_type' => ActivityLog::TYPE_ADJUSTMENT_APPLIED,
                'description'   => "Adjustment direset pada project #{$project->id}.",
                'metadata'      => ['method' => 'reset'],
            ]);
        });
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    private function equalCorrection(string $unit, bool $negative, int $index): string
    {
        $corr = bcmul($unit, (string) $index, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }

    private function bowditchCorrection(
        string $fh,
        bool $negative,
        string $cumDist,
        string $totalDist
    ): string {
        if (bccomp($totalDist, '0', self::SCALE) === 0) return '0';
        $ratio = bcdiv($cumDist, $totalDist, self::SCALE);
        $corr  = bcmul($fh, $ratio, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }
}
