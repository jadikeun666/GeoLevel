use App\Events\SurveyRecalculated;
use Illuminate\Support\Facades\DB;

class LevelingCalculationService
{
    private const SCALE = 10;

public function recalculate(Project $project, int $userId): void
{
    DB::transaction(function () use ($project, $userId) {
        $project->load([
            'readings' => fn ($q) => $q->orderBy('sequence_no')
        ]);

        if ($project->readings->isEmpty()) {
            $this->clearComputedElevations($project);
            return;
        }

        $computed = $this->calculateElevations($project);
        $closure  = $this->calculateClosure($project, $computed);
        $adjusted = $this->applyAdjustment($project, $computed, $closure);

        $this->persistResults($project, $adjusted, $closure);

        $this->logActivity(
            $project,
            $userId,
            'survey_recalculated',
            [
                'closure_error'     => $closure['fh'],
                'total_distance_km' => $closure['total_distance_km'],
                'status'            => $closure['status'],
            ]
        );
    });

    SurveyRecalculated::dispatch($project->id, $userId);
}

    public function calculateElevations(Project $project): array
    {
        $results            = [];
        $currentElevation   = number_format((float) $project->benchmark_elevation, self::SCALE, '.', '');
        $currentHI          = '0';
        $cumulativeDistance = '0';

        foreach ($project->readings as $reading) {
            $bt       = (string) $reading->bt;
            $distance = $reading->effectiveDistance();

            $cumulativeDistance = bcadd($cumulativeDistance, $distance, self::SCALE);

            if ($reading->isBacksight()) {
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

        $fh = bcsub($sumBS, $sumFS, self::SCALE);
        if (bccomp($fh, '0', self::SCALE) < 0) {
            $fh = bcsub('0', $fh, self::SCALE);
        }

        $totalDistanceKm  = bcdiv($totalDistanceM, '1000', self::SCALE);
        $toleranceConst   = (string) config("geolevel.tolerance_classes.{$project->tolerance_class}");
        $allowedTolerance = bcmul($toleranceConst, $this->bcSqrt($totalDistanceKm), self::SCALE);
        $status           = bccomp($fh, $allowedTolerance, self::SCALE) <= 0 ? 'accepted' : 'rejected';

        return [
            'fh'                => $fh,
            'sum_bs'            => $sumBS,
            'sum_fs'            => $sumFS,
            'total_distance_m'  => $totalDistanceM,
            'total_distance_km' => $totalDistanceKm,
            'allowed_tolerance' => $allowedTolerance,
            'status'            => $status,
            'n_fs'              => $nFS,
        ];
    }

    public function applyAdjustment(Project $project, array $computed, array $closure): array
    {
        $method   = $project->adjustment_method ?? config('geolevel.default_adjustment_method');
        $fh       = $closure['fh'];
        $totalD   = $closure['total_distance_m'];
        $nFS      = $closure['n_fs'];

        // Tanda koreksi: ΣBS > ΣFS → fh positif → koreksi negatif
        $fhSigned = bcsub($closure['sum_bs'], $closure['sum_fs'], self::SCALE);
        $negative = bccomp($fhSigned, '0', self::SCALE) >= 0;

        // Untuk equal: correction per titik = fh / n
        // Koreksi KUMULATIF: titik ke-i mendapat i × (fh/n)
        $unitCorrection = $nFS > 0 ? bcdiv($fh, (string) $nFS, self::SCALE) : '0';
        $fsCounter      = 0;

        foreach ($computed as &$row) {
            if ($row['reading_type'] === 'BS') {
                $row['correction']         = '0';
                $row['adjusted_elevation'] = $row['raw_elevation'];
                continue;
            }

            // IS dan FS keduanya mendapat koreksi
            $fsCounter++;

            $correction = match ($method) {
                'bowditch' => $this->bowditchCorrection($fh, $negative, $row['cumulative_distance'], $totalD),
                default    => $this->equalCorrectionCumulative($unitCorrection, $negative, $fsCounter),
            };

            $row['correction']         = $correction;
            $row['adjusted_elevation'] = bcadd($row['raw_elevation'], $correction, self::SCALE);
        }
        unset($row);

        return $computed;
    }

    private function equalCorrectionCumulative(string $unit, bool $negative, int $index): string
    {
        // correction_i = -(unit × index)
        $corr = bcmul($unit, (string) $index, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }

    private function bowditchCorrection(string $fh, bool $negative, string $cumDist, string $totalDist): string
    {
        if (bccomp($totalDist, '0', self::SCALE) === 0) return '0';
        $ratio = bcdiv($cumDist, $totalDist, self::SCALE);
        $corr  = bcmul($fh, $ratio, self::SCALE);
        return $negative ? bcsub('0', $corr, self::SCALE) : $corr;
    }

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

    private function bcSqrt(string $n, int $scale = 10): string
    {
        if (bccomp($n, '0', $scale) <= 0) return '0';
        $x = number_format(sqrt((float) $n), $scale, '.', '');
        for ($i = 0; $i < 20; $i++) {
            $prev = $x;
            $x    = bcdiv(bcadd($x, bcdiv($n, $x, $scale + 2), $scale + 2), '2', $scale + 2);
            if (bccomp($x, $prev, $scale) === 0) break;
        }
        return $x;
    }

    private function round(string $value, int $decimals): string
    {
        return number_format((float) $value, $decimals, '.', '');
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
): void
{
    ActivityLog::create([
        'project_id'    => $project->id,
        'user_id'       => $userId,
        'activity_type' => $type,
        'description'   => "Survey recalculated for project #{$project->id}",
        'metadata'      => $metadata,
    ]);
}
}
