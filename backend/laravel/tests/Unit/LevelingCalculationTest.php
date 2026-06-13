<?php

namespace Tests\Unit;
use PHPUnit\Framework\Attributes\Test;

use App\Services\LevelingCalculationService;
use App\Services\ClosureCheckerService;
use App\Services\AdjustmentService;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Canonical regression test using the worked example in docs/formulas.md.
 *
 * All expected values are taken verbatim from that document.
 * Do NOT change expected values without also updating docs/formulas.md.
 */
class LevelingCalculationTest extends TestCase
{
    use RefreshDatabase;

    // ── Fixtures (dari formulas.md) ──────────────────────────────────────────

    private const BM_ELEVATION = '100.0000';

    private const READINGS = [
        ['seq' => 1, 'point' => 'BM-A', 'type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970'],
        ['seq' => 2, 'point' => 'TP-1', 'type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630'],
        ['seq' => 3, 'point' => 'TP-1', 'type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890'],
        ['seq' => 4, 'point' => 'TP-2', 'type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700'],
        ['seq' => 5, 'point' => 'TP-2', 'type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300'],
        ['seq' => 6, 'point' => 'BM-B', 'type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880'],
    ];

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Create a real project + readings in DB, run calculation, return computed_elevations. */
    private function seedAndCalculate(): array
    {
        $user = \App\Models\User::factory()->create();

        $project = \App\Models\Project::factory()->create([
            'user_id'             => $user->id,
            'benchmark_elevation' => self::BM_ELEVATION,
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
        ]);

        \App\Models\Reading::withoutEvents(function () use ($project) {
            foreach (self::READINGS as $r) {
                $project->readings()->create([
                    'sequence_no'  => $r['seq'],
                    'point_name'   => $r['point'],
                    'reading_type' => $r['type'],
                    'ba'           => $r['ba'],
                    'bt'           => $r['bt'],
                    'bb'           => $r['bb'],
                ]);
            }
        });

        $service = app(LevelingCalculationService::class);
        $service->recalculate($project->fresh(), $user->id);

        return [
            'project'    => $project->fresh(),
            'user'       => $user,
            'elevations' => \App\Models\ComputedElevation::where('project_id', $project->id)
                               ->orderBy('sequence_no')->get(),
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // BT Validation tests
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function bt_computed_formula_is_correct(): void
    {
        // BT_computed = (BA + BB) / 2
        // Seq 1: (1.5230 + 0.8970) / 2 = 1.2100 — deviasi = 0.0000
        $ba = '1.5230';
        $bb = '0.8970';
        $btField = '1.2100';

        $computed  = bcdiv(bcadd($ba, $bb, 10), '2', 10);
        $deviation = bcsub($btField > $computed ? $btField : $computed,
                           $btField > $computed ? $computed : $btField, 6);

        $this->assertEqualsWithDelta(0.0, (float) $deviation, 0.000001,
            'BT deviation for seq-1 should be 0');
    }

    #[Test]
    public function bt_deviation_below_limit_passes(): void
    {
        // Any deviation ≤ 0.002 must be accepted
        $limit = config('geolevel.bt_deviation_limit', 0.002);
        $this->assertLessThanOrEqual(0.002, $limit);

        // Seq-1 has 0.000 deviation
        $deviation = abs(1.2100 - (1.5230 + 0.8970) / 2);
        $this->assertLessThanOrEqual($limit, $deviation);
    }

    #[Test]
    public function bt_deviation_above_limit_fails(): void
    {
        $limit = config('geolevel.bt_deviation_limit', 0.002);

        // Deliberately bad reading: bt is 0.010 off
        $ba = 1.5230;
        $bb = 0.8970;
        $btBad = 1.2200; // 0.0100 deviation

        $computed  = ($ba + $bb) / 2;
        $deviation = abs($btBad - $computed);

        $this->assertGreaterThan($limit, $deviation,
            'A deviation of 0.010 must exceed the 0.002 limit');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Elevation calculation tests (canonical worked example)
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function hi_bm_a_is_correct(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        // HI(BM-A) = 100.0000 + 1.2100 = 101.2100
        $bsRow = $elevs->firstWhere('sequence_no', 1);
        $this->assertNotNull($bsRow, 'BM-A BS row must exist');
        $this->assertEqualsWithDelta(101.2100, (float) $bsRow->hi, 0.0001,
            'HI(BM-A) must be 101.2100');
    }

    #[Test]
    public function elevation_tp1_is_correct(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        // Elev(TP-1) = 101.2100 − 1.1750 = 100.0350
        $fsRow = $elevs->firstWhere('sequence_no', 2);
        $this->assertEqualsWithDelta(100.0350, (float) $fsRow->raw_elevation, 0.0001,
            'Elevation of TP-1 must be 100.0350');
    }

    #[Test]
    public function hi_tp1_is_correct(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        // HI(TP-1) = 100.0350 + 1.3050 = 101.3400
        $bsRow = $elevs->firstWhere('sequence_no', 3);
        $this->assertEqualsWithDelta(101.3400, (float) $bsRow->hi, 0.0001,
            'HI(TP-1) must be 101.3400');
    }

    #[Test]
    public function elevation_tp2_is_correct(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        // Elev(TP-2) = 101.3400 − 1.0820 = 100.2580
        $fsRow = $elevs->firstWhere('sequence_no', 4);
        $this->assertEqualsWithDelta(100.2580, (float) $fsRow->raw_elevation, 0.0001,
            'Elevation of TP-2 must be 100.2580');
    }

    #[Test]
    public function elevation_bm_b_is_correct(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        // Elev(BM-B) = 101.5010 − 1.1000 = 100.4010
        $fsRow = $elevs->firstWhere('sequence_no', 6);
        $this->assertEqualsWithDelta(100.4010, (float) $fsRow->raw_elevation, 0.0001,
            'Elevation of BM-B must be 100.4010');
    }

    #[Test]
    public function all_corrections_zero_before_adjustment(): void
    {
        ['elevations' => $elevs] = $this->seedAndCalculate();

        foreach ($elevs as $e) {
            $this->assertEqualsWithDelta(0.0, (float) $e->correction, 0.000001,
                "correction for seq {$e->sequence_no} must be 0 before adjustment");
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Closure error tests
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function closure_error_fh_is_correct(): void
    {
        // ΣBS = 1.2100 + 1.3050 + 1.2430 = 3.7580
        // ΣFS = 1.1750 + 1.0820 + 1.1000 = 3.3570
        // fh  = |3.7580 − 3.3570| = 0.4010
        ['project' => $project] = $this->seedAndCalculate();

        $this->assertEqualsWithDelta(0.401000, (float) $project->closure_error, 0.000001,
            'fh must be 0.401000 m');
    }

    #[Test]
    public function allowed_tolerance_la_is_correct(): void
    {
        // distance = 375.6 m = 0.3756 km
        // tolerance (LA, c=4mm) = 4 × √0.3756 = 0.002452 m  (approx)
        ['project' => $project] = $this->seedAndCalculate();

        // We allow ±0.0001 because √ precision may vary slightly
        $this->assertEqualsWithDelta(0.002452, (float) $project->allowed_tolerance, 0.0001,
            'Tolerance (LA) must be ≈ 0.002452 m');
    }

    #[Test]
    public function project_status_is_rejected_because_fh_exceeds_tolerance(): void
    {
        ['project' => $project] = $this->seedAndCalculate();

        $this->assertSame('rejected', $project->status,
            'Project must be rejected: fh (0.401) >> tolerance (0.002452)');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Equal distribution adjustment tests
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function equal_adjustment_correction_per_point(): void
    {
        // correction_each = −0.4010 / 3 = −0.133667
        ['project' => $project, 'user' => $user, 'elevations' => $elevs] = $this->seedAndCalculate();

        $adjService = app(AdjustmentService::class);
        $adjService->applyToProject($project, 'equal', $user->id);

        $elevs = \App\Models\ComputedElevation::where('project_id', $project->id)
            ->orderBy('sequence_no')->get();

        $fsRows = $elevs->whereIn('sequence_no', [2, 4, 6])->values();

        // Each FS point gets cumulative correction: −0.133667 × n
        $this->assertEqualsWithDelta(-0.133667, (float) $fsRows[0]->correction, 0.000001,
            'TP-1 correction must be -0.133667');
        $this->assertEqualsWithDelta(-0.267333, (float) $fsRows[1]->correction, 0.000001,
            'TP-2 correction must be -0.267333');
        $this->assertEqualsWithDelta(-0.401000, (float) $fsRows[2]->correction, 0.000001,
            'BM-B correction must be -0.401000');
    }

    #[Test]
    public function equal_adjustment_tp1_adjusted_elevation(): void
    {
        // Elev(TP-1) adj = 100.0350 − 0.133667 = 99.901333
        ['project' => $project, 'user' => $user] = $this->seedAndCalculate();
        $adjService = app(AdjustmentService::class);
        $adjService->applyToProject($project, 'equal', $user->id);

        $tp1 = \App\Models\ComputedElevation::where('project_id', $project->id)
            ->where('sequence_no', 2)->first();

        $this->assertEqualsWithDelta(99.9013, (float) $tp1->adjusted_elevation, 0.0001,
            'TP-1 adjusted elevation must be ≈ 99.9013');
    }

    #[Test]
    public function equal_adjustment_tp2_adjusted_elevation(): void
    {
        // Elev(TP-2) adj = 100.2580 − 0.267333 = 99.990667
        ['project' => $project, 'user' => $user] = $this->seedAndCalculate();
        $adjService = app(AdjustmentService::class);
        $adjService->applyToProject($project, 'equal', $user->id);

        $tp2 = \App\Models\ComputedElevation::where('project_id', $project->id)
            ->where('sequence_no', 4)->first();

        $this->assertEqualsWithDelta(99.9907, (float) $tp2->adjusted_elevation, 0.0001,
            'TP-2 adjusted elevation must be ≈ 99.9907');
    }

    #[Test]
    public function equal_adjustment_bm_b_adjusted_elevation(): void
    {
        // Elev(BM-B) adj = 100.4010 − 0.401000 = 100.000000
        ['project' => $project, 'user' => $user] = $this->seedAndCalculate();
        $adjService = app(AdjustmentService::class);
        $adjService->applyToProject($project, 'equal', $user->id);

        $bmB = \App\Models\ComputedElevation::where('project_id', $project->id)
            ->where('sequence_no', 6)->first();

        $this->assertEqualsWithDelta(100.0000, (float) $bmB->adjusted_elevation, 0.0001,
            'BM-B adjusted elevation must be 100.0000');
    }

    #[Test]
    public function adjustment_is_reversible_reset_to_zero(): void
    {
        ['project' => $project, 'user' => $user] = $this->seedAndCalculate();
        $adjService = app(AdjustmentService::class);

        $adjService->applyToProject($project, 'equal', $user->id);
        $adjService->reset($project, $user->id);

        $elevs = \App\Models\ComputedElevation::where('project_id', $project->id)->get();
        foreach ($elevs as $e) {
            $this->assertEqualsWithDelta(0.0, (float) $e->correction, 0.000001,
                "After reset, correction for seq {$e->sequence_no} must be 0");
            $this->assertEqualsWithDelta(
                (float) $e->raw_elevation,
                (float) $e->adjusted_elevation,
                0.0001,
                "After reset, adjusted_elevation must equal raw_elevation for seq {$e->sequence_no}"
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Idempotency test
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function recalculation_is_idempotent(): void
    {
        ['project' => $project] = $this->seedAndCalculate();
        $service = app(LevelingCalculationService::class);
        $user    = $project->user_id;

        // Run again — result must be identical
        $service->recalculate($project->fresh(), $user);
        $service->recalculate($project->fresh(), $user);

        $elevs = \App\Models\ComputedElevation::where('project_id', $project->id)
            ->orderBy('sequence_no')->get();

        $bm_b = $elevs->firstWhere('sequence_no', 6);
        $this->assertEqualsWithDelta(100.4010, (float) $bm_b->raw_elevation, 0.0001,
            'BM-B raw elevation must remain 100.4010 after multiple recalculations');
    }

    // ════════════════════════════════════════════════════════════════════════
    // Optical distance test
    // ════════════════════════════════════════════════════════════════════════

    #[Test]
    public function optical_distance_formula_is_correct(): void
    {
        // D = (BA − BB) × 100
        // Seq-1: (1.5230 − 0.8970) × 100 = 62.60 m
        $ba = 1.5230;
        $bb = 0.8970;
        $expected = ($ba - $bb) * 100;

        $this->assertEqualsWithDelta(62.60, $expected, 0.01,
            'Optical distance for seq-1 must be 62.60 m');
    }

}