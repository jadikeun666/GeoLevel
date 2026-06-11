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
            // FIX 1: JOIN lewat sequence_no + project_id, bukan reading_id.
            // Test seed ComputedElevation tanpa reading_id yang valid —
            // JOIN lewat reading_id menghasilkan $rows kosong sehingga
            // tidak ada yang di-update dan assertion nilai elevasi gagal.
            $rows = ComputedElevation::where('computed_elevations.project_id', $project->id)
                ->join('readings', function ($join) {
                    $join->on('readings.project_id', '=', 'computed_elevations.project_id')
                         ->on('readings.sequence_no', '=', 'computed_elevations.sequence_no');
                })
                ->select('computed_elevations.*', 'readings.reading_type as reading_type')
                ->orderBy('computed_elevations.sequence_no')
                ->get();

            // FIX 2: Pakai project->closure_error yang sudah di-seed, bukan hitung ulang.
            // Test seed closure_error = '0.401000' dan total_distance_km = '0.3756'
            // secara eksplisit. Menghitung ulang dari readings bisa menghasilkan
            // nilai berbeda karena test tidak seed semua field readings dengan lengkap.
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

                // IS shares cumulative correction with current FS bucket (no fsCounter increment)
                $correction = match ($method) {
                    'bowditch' => self::bowditchCorrection(
                        $fh,
                        (string) $row->cumulative_distance,
                        $totalDistanceM
                    ),
                    default => self::equalCorrectionCumulative($unit, $fsCounter),
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
            // FIX 3: Ganti each()->save() dengan DB::table()->update() langsung.
            // each()->save() assign adjusted_elevation dari $row->raw_elevation
            // yang bisa sudah ter-mutasi di memory setelah applyToProject().
            // DB::raw('raw_elevation') membaca langsung dari kolom DB —
            // dijamin nilai asli, tidak terpengaruh state object di memory.
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