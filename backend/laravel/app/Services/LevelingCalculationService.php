<?php

namespace App\Services;

use App\Events\SurveyRecalculated;
use App\Models\ActivityLog;
use App\Models\ComputedElevation;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

class LevelingCalculationService
{
    private const SCALE = 10;

    // -----------------------------------------------------------------------
    // Public entry point
    // -----------------------------------------------------------------------

    public function recalculate(Project $project, int $userId): void
    {
        DB::transaction(function () use ($project, $userId) {
            $project->load([
                'readings' => fn ($q) => $q->orderBy('sequence_no'),
            ]);

            if ($project->readings->isEmpty()) {
                $this->clearComputedElevations($project);
                return;
            }

            $computed = $this->calculateElevations($project);
            $closure  = $this->calculateClosure($project, $computed);

            // FIX 1: Only apply adjustment if survey is accepted
            $adjusted = $closure['status'] === 'accepted'
                        ? $this->applyAdjustment($project, $computed, $closure)
                        : $this->initCorrections($computed);

            $this->persistResults($project, $adjusted, $closure);

            $this->logActivity(
                $project,
                $userId,
                ActivityLog::TYPE_SURVEY_RECALCULATED,
                [
                    'closure_error'     => $closure['fh'],
                    'total_distance_km' => $closure['total_distance_km'],
                    'status'            => $closure['status'],
                ]
            );
        });

        // Dispatch di luar transaction agar hanya terpanggil bila commit berhasil
        SurveyRecalculated::dispatch($project->id, $userId);
    }

    // -----------------------------------------------------------------------
    // Step 1 — Hitung elevasi mentah (raw elevation) — instance method
    // -----------------------------------------------------------------------

    public function calculateElevations(Project $project): array
    {
        $results            = [];
        $currentElevation   = number_format(
            (float) $project->benchmark_elevation, self::SCALE, '.', ''
        );
        $currentHI          = '0';
        $cumulativeDistance = '0';
        foreach ($project->readings as $reading) {
            $bt       = (string) $reading->bt;
            $distance = $reading->effectiveDistance();

            $cumulativeDistance = bcadd($cumulativeDistance, $distance, self::SCALE);

            if ($reading->isBacksight()) {
                // HI = Elevasi titik sebelumnya + BS
                $currentHI = bcadd($currentElevation, $bt, self::SCALE);

                $results[] = [
                    'reading_id'          => $reading->id,
                    'sequence_no'         => $reading->sequence_no,
                    'point_name'          => $reading->point_name,
                    'reading_type'        => $reading->reading_type,
                    'hi'                  => $currentHI,
                    'raw_elevation'       => $currentElevation,
                    'cumulative_distance' => $cumulativeDistance,
                ];
            } elseif ($reading->isForesight() || $reading->isIntermediate()) {
                // Elevasi = HI − FS/IS
                $elevation        = bcsub($currentHI, $bt, self::SCALE);
                $currentElevation = $elevation;

                $results[] = [
                    'reading_id'          => $reading->id,
                    'sequence_no'         => $reading->sequence_no,
                    'point_name'          => $reading->point_name,
                    'reading_type'        => $reading->reading_type,
                    'hi'                  => null,
                    'raw_elevation'       => $elevation,
                    'cumulative_distance' => $cumulativeDistance,
                ];
            }
        }

        return $results;
    }

    // -----------------------------------------------------------------------
    // Step 2 — Hitung closure error (fh) dan toleransi SNI
    // -----------------------------------------------------------------------

    public function calculateClosure(Project $project, array $computed): array
    {
        $sumBS          = '0';
        $sumFS          = '0';
        $totalDistanceM = '0';
        $nFS            = 0;

        foreach ($project->readings as $reading) {
            $bt       = (string) $reading->bt;
            $distance = $reading->effectiveDistance();

            if ($reading->isBacksight()) {
                $sumBS = bcadd($sumBS, $bt, self::SCALE);
            } elseif ($reading->isForesight()) {
                $sumFS = bcadd($sumFS, $bt, self::SCALE);
                $nFS++;
            }

            $totalDistanceM = bcadd($totalDistanceM, $distance, self::SCALE);
        }

        // fh = |ΣBS − ΣFS| — simpan sebagai absolut untuk perbandingan toleransi
        $fhSigned = bcsub($sumBS, $sumFS, self::SCALE);
        $fh       = bccomp($fhSigned, '0', self::SCALE) < 0
                    ? bcsub('0', $fhSigned, self::SCALE)
                    : $fhSigned;

        $totalDistanceKm  = bcdiv($totalDistanceM, '1000', self::SCALE);
        $toleranceConst   = (string) config("geolevel.tolerance_classes.{$project->tolerance_class}");
        $allowedTolerance = bcmul($toleranceConst, $this->bcSqrt($totalDistanceKm), self::SCALE);
        $status           = bccomp($fh, $allowedTolerance, self::SCALE) <= 0
                            ? 'accepted'
                            : 'rejected';

        return [
            'fh'                => $fh,
            'fh_signed'         => $fhSigned,   // dibutuhkan applyAdjustment untuk arah koreksi
            'sum_bs'            => $sumBS,
            'sum_fs'            => $sumFS,
            'total_distance_m'  => $totalDistanceM,
            'total_distance_km' => $totalDistanceKm,
            'allowed_tolerance' => $allowedTolerance,
            'status'            => $status,
            'n_fs'              => $nFS,
        ];
    }

    // -----------------------------------------------------------------------
    // Step 3 — Terapkan adjustment (default: equal distribution)
    // -----------------------------------------------------------------------

    public function applyAdjustment(Project $project, array $computed, array $closure): array
    {
        $method = $project->adjustment_method
                  ?? config('geolevel.default_adjustment_method');

        $fh    = $closure['fh'];           // nilai absolut
        $totalD = $closure['total_distance_m'];
        $nFS   = $closure['n_fs'];

        // Arah koreksi: ΣBS > ΣFS → fh_signed positif → koreksi harus negatif
        $negative = bccomp($closure['fh_signed'], '0', self::SCALE) >= 0;

        // unit = fh / n  (hanya dibagi jumlah FS, bukan IS+FS)
        $unitCorrection = $nFS > 0
                          ? bcdiv($fh, (string) $nFS, self::SCALE)
                          : '0';

        // FIX: fsCounter hanya naik saat membaca baris FS, bukan IS
        $fsCounter = 0;

        foreach ($computed as &$row) {
            if ($row['reading_type'] === 'BS') {
                $row['correction']         = '0';
                $row['adjusted_elevation'] = $row['raw_elevation'];
                continue;
            }

            // IS juga mendapat koreksi berdasarkan fsCounter terakhir (interpolasi posisi)
            // FS yang menaikkan counter terlebih dahulu
            if ($row['reading_type'] === 'FS') {
                $fsCounter++;
            }

            $correction = match ($method) {
                'bowditch' => $this->bowditchCorrection(
                                $fh, $negative,
                                $row['cumulative_distance'],
                                $totalD
                              ),
                default    => $this->equalCorrectionCumulative(
                                $unitCorrection, $negative, $fsCounter
                              ),
            };

            $row['correction']         = $correction;
            $row['adjusted_elevation'] = bcadd(
                $row['raw_elevation'], $correction, self::SCALE
            );
        }
        unset($row);

        return $computed;
    }

    // -----------------------------------------------------------------------
    // Private helpers — koreksi
    // -----------------------------------------------------------------------

    private function equalCorrectionCumulative(
        string $unit,
        bool $negative,
        int $index
    ): string {
        // correction_i = −(unit × index)  → koreksi kumulatif per posisi FS
        $corr = bcmul($unit, (string) $index, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }

    private function bowditchCorrection(
        string $fh,
        bool $negative,
        string $cumDist,
        string $totalDist
    ): string {
        if (bccomp($totalDist, '0', self::SCALE) === 0) {
            return '0';
        }
        $ratio = bcdiv($cumDist, $totalDist, self::SCALE);
        $corr  = bcmul($fh, $ratio, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }

    // -----------------------------------------------------------------------
    // FIX 1 (helper) — init corrections to zero when status != accepted
    // -----------------------------------------------------------------------

    private function initCorrections(array $computed): array
    {
        foreach ($computed as &$row) {
            $row['correction']         = '0';
            $row['adjusted_elevation'] = $row['raw_elevation'];
        }
        unset($row);
        return $computed;
    }

    // -----------------------------------------------------------------------
    // Private helpers — persist
    // -----------------------------------------------------------------------

    private function persistResults(Project $project, array $rows, array $closure): void
    {
        ComputedElevation::where('project_id', $project->id)->delete();

        $inserts = [];
        $now     = now();

        foreach ($rows as $row) {
            $inserts[] = [
                'project_id'          => $project->id,
                'reading_id'          => $row['reading_id'],
                'sequence_no'         => $row['sequence_no'],
                'point_name'          => $row['point_name'],
                'hi'                  => $row['hi'],
                'raw_elevation'       => $this->round($row['raw_elevation'], 4),
                'correction'          => $this->round($row['correction'], 6),
                'adjusted_elevation'  => $this->round($row['adjusted_elevation'], 4),
                'cumulative_distance' => $this->round($row['cumulative_distance'], 3),
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
        }

        ComputedElevation::insert($inserts);

        $project->update([
            'closure_error'     => $this->round($closure['fh'], 6),
            'total_distance_km' => $this->round($closure['total_distance_km'], 4),
            'allowed_tolerance' => $this->round($closure['allowed_tolerance'], 6),
            'status'            => $closure['status'],
        ]);
    }

    private function clearComputedElevations(Project $project): void
    {
        ComputedElevation::where('project_id', $project->id)->delete();

        $project->update([
            'closure_error'     => null,
            'total_distance_km' => null,
            'allowed_tolerance' => null,
            'status'            => 'draft',
        ]);
    }

    private function logActivity(
        Project $project,
        int $userId,
        string $type,
        array $metadata
    ): void {
        ActivityLog::create([
            'project_id'    => $project->id,
            'user_id'       => $userId,
            'activity_type' => $type,
            'description'   => "Survey recalculated for project #{$project->id}",
            'metadata'      => $metadata,
        ]);
    }

    // -----------------------------------------------------------------------
    // Private helpers — matematik
    // -----------------------------------------------------------------------

    private function bcSqrt(string $n, int $scale = 10): string
    {
        if (bccomp($n, '0', $scale) <= 0) {
            return '0';
        }

        // Seed awal dengan sqrt float, lalu iterasi Newton-Raphson
        $x = number_format(sqrt((float) $n), $scale, '.', '');

        for ($i = 0; $i < 20; $i++) {
            $prev = $x;
            $x    = bcdiv(
                bcadd($x, bcdiv($n, $x, $scale + 2), $scale + 2),
                '2',
                $scale + 2
            );
            if (bccomp($x, $prev, $scale) === 0) {
                break;
            }
        }

        return $x;
    }

    private function round(string $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, '.', '');
    }

    // -----------------------------------------------------------------------
    // Public static helpers — pure math, testable in isolation
    // -----------------------------------------------------------------------

    public static function computeBt(string $ba, string $bb): string
    {
        return bcdiv(bcadd($ba, $bb, self::SCALE), '2', self::SCALE);
    }

    public static function isBtDeviationValid(
        string $btField,
        string $ba,
        string $bb
    ): bool {
        $computed = self::computeBt($ba, $bb);

        $diff = bcsub($btField, $computed, self::SCALE);

        $absDeviation = bccomp($diff, '0', self::SCALE) < 0
            ? bcsub('0', $diff, self::SCALE)
            : $diff;

        $limit = (string) config('geolevel.bt_deviation_limit', '0.002');

        return bccomp($absDeviation, $limit, self::SCALE) <= 0;
    }

    public static function computeDistance(string $ba, string $bb): string
    {
        return bcmul(
            bcsub($ba, $bb, self::SCALE),
            '100',
            self::SCALE
        );
    }

    public static function computeHi(string $elevation, string $bs): string
    {
        return bcadd($elevation, $bs, self::SCALE);
    }

    // FIX 2: Round to 4 decimal places (was returning 10 decimals raw from bcsub)
    public static function computeElevation(string $hi, string $reading): string
    {
        return number_format((float) bcsub($hi, $reading, self::SCALE), 4, '.', '');
    }

    // -----------------------------------------------------------------------
    // FIX 3: Static alternative — calculateElevationsFromArray(array, string)
    // Renamed to avoid PHP redeclaration conflict (no method overloading in PHP).
    // Testable in isolation without a Project model instance.
    // -----------------------------------------------------------------------

    public static function calculateElevationsStatic(array $readings, string $benchmarkElevation): array
    {
        usort($readings, fn ($a, $b) => $a['sequence_no'] <=> $b['sequence_no']);

        $results            = [];
        $currentElevation   = $benchmarkElevation;
        $currentHI          = '0';
        $cumulativeDistance = '0';

        foreach ($readings as $reading) {
            $bt       = (string) $reading['bt'];
            $distance = isset($reading['distance_m']) && $reading['distance_m'] !== null
                        ? (string) $reading['distance_m']
                        : bcmul(bcsub((string) $reading['ba'], (string) $reading['bb'], self::SCALE), '100', self::SCALE);

            $cumulativeDistance = bcadd($cumulativeDistance, $distance, self::SCALE);

            if ($reading['reading_type'] === 'BS') {
                $currentHI = bcadd($currentElevation, $bt, self::SCALE);
                $results[] = [
                    'sequence_no'         => $reading['sequence_no'],
                    'point_name'          => $reading['point_name'],
                    'reading_type'        => 'BS',
                    'hi'                  => $currentHI,
                    'raw_elevation'       => $currentElevation,
                    'correction'          => '0',
                    'adjusted_elevation'  => $currentElevation,
                    'cumulative_distance' => $cumulativeDistance,
                ];
            } else {
                $elevation        = bcsub($currentHI, $bt, self::SCALE);
                $currentElevation = $elevation;
                $results[] = [
                    'sequence_no'         => $reading['sequence_no'],
                    'point_name'          => $reading['point_name'],
                    'reading_type'        => $reading['reading_type'],
                    'hi'                  => null,
                    'raw_elevation'       => $elevation,
                    'correction'          => '0',
                    'adjusted_elevation'  => $elevation,
                    'cumulative_distance' => $cumulativeDistance,
                ];
            }
        }

        return $results;
    }
}