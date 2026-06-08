<?php

namespace Tests\Unit\Services;
use PHPUnit\Framework\Attributes\Test;

use App\Services\LevelingCalculationService;
use Tests\TestCase;

/**
 * Unit tests for LevelingCalculationService.
 *
 * Canonical dataset (regression seed) from docs/formulas.md:
 *
 *   BM-A elevation = 100.0000 m
 *
 *   Seq | Point | Type | BA     | BT     | BB
 *   ----|-------|------|--------|--------|-------
 *    1  | BM-A  | BS   | 1.5230 | 1.2100 | 0.8970
 *    2  | TP-1  | FS   | 1.4870 | 1.1750 | 0.8630
 *    3  | TP-1  | BS   | 1.6210 | 1.3050 | 0.9890
 *    4  | TP-2  | FS   | 1.3940 | 1.0820 | 0.7700
 *    5  | TP-2  | BS   | 1.5560 | 1.2430 | 0.9300
 *    6  | BM-B  | FS   | 1.4120 | 1.1000 | 0.7880
 *
 * Expected results:
 *   HI(BM-A)   = 101.2100
 *   Elev(TP-1) = 100.0350
 *   HI(TP-1)   = 101.3400
 *   Elev(TP-2) = 100.2580
 *   HI(TP-2)   = 101.5010
 *   Elev(BM-B) = 100.4010
 *   ΣBS        = 3.7580
 *   ΣFS        = 3.3570
 *   fh         = 0.4010
 */
class LevelingCalculationServiceTest extends TestCase
{
    private const DELTA_6 = 0.000001; // 6 decimal places — for corrections/fh
    private const DELTA_4 = 0.0001;   // 4 decimal places — for elevations

    // ---------------------------------------------------------------------------
    // BT_computed = (BA + BB) / 2
    // ---------------------------------------------------------------------------

    #[Test]
    public function bt_computed_equals_average_of_ba_and_bb(): void
    {
        $bt = LevelingCalculationService::computeBt('1.5230', '0.8970');
        $this->assertEqualsWithDelta(1.2100, (float) $bt, self::DELTA_4);
    }

    #[Test]
    public function bt_computed_for_all_canonical_readings(): void
    {
        $cases = [
            ['ba' => '1.5230', 'bb' => '0.8970', 'expected' => 1.2100],
            ['ba' => '1.4870', 'bb' => '0.8630', 'expected' => 1.1750],
            ['ba' => '1.6210', 'bb' => '0.9890', 'expected' => 1.3050],
            ['ba' => '1.3940', 'bb' => '0.7700', 'expected' => 1.0820],
            ['ba' => '1.5560', 'bb' => '0.9300', 'expected' => 1.2430],
            ['ba' => '1.4120', 'bb' => '0.7880', 'expected' => 1.1000],
        ];

        foreach ($cases as $case) {
            $bt = LevelingCalculationService::computeBt($case['ba'], $case['bb']);
            $this->assertEqualsWithDelta(
                $case['expected'],
                (float) $bt,
                self::DELTA_4,
                "BT mismatch for BA={$case['ba']}, BB={$case['bb']}"
            );
        }
    }

    // ---------------------------------------------------------------------------
    // BT deviation validation: |BT_field − BT_computed| <= 0.002 m
    // ---------------------------------------------------------------------------

    #[Test]
    public function bt_deviation_passes_when_within_limit(): void
    {
        // BT_field == BT_computed → deviation = 0.000
        $this->assertTrue(
            LevelingCalculationService::isBtDeviationValid('1.2100', '1.5230', '0.8970')
        );
    }

    #[Test]
    public function bt_deviation_passes_at_exact_limit(): void
    {
        // BT_computed = 1.2100; BT_field = 1.2120 → deviation = 0.002 (exactly at limit)
        $this->assertTrue(
            LevelingCalculationService::isBtDeviationValid('1.2120', '1.5230', '0.8970')
        );
    }

    #[Test]
    public function bt_deviation_fails_when_exceeds_limit(): void
    {
        // BT_computed = 1.2100; BT_field = 1.2130 → deviation = 0.003 > 0.002
        $this->assertFalse(
            LevelingCalculationService::isBtDeviationValid('1.2130', '1.5230', '0.8970')
        );
    }

    #[Test]
    public function bt_deviation_passes_just_below_limit(): void
    {
        // deviation = 0.0019
        $this->assertTrue(
            LevelingCalculationService::isBtDeviationValid('1.2119', '1.5230', '0.8970')
        );
    }

    // ---------------------------------------------------------------------------
    // D = (BA - BB) × 100   (optical distance)
    // ---------------------------------------------------------------------------

    #[Test]
    public function optical_distance_computed_correctly(): void
    {
        // Leg 1 BM-A: (1.5230 - 0.8970) × 100 = 62.60 m
        $d = LevelingCalculationService::computeDistance('1.5230', '0.8970');
        $this->assertEqualsWithDelta(62.60, (float) $d, self::DELTA_4);
    }

    #[Test]
    public function optical_distance_for_all_canonical_readings(): void
    {
        $cases = [
            ['ba' => '1.5230', 'bb' => '0.8970', 'expected' => 62.60],
            ['ba' => '1.4870', 'bb' => '0.8630', 'expected' => 62.40],
            ['ba' => '1.6210', 'bb' => '0.9890', 'expected' => 63.20],
            ['ba' => '1.3940', 'bb' => '0.7700', 'expected' => 62.40],
            ['ba' => '1.5560', 'bb' => '0.9300', 'expected' => 62.60],
            ['ba' => '1.4120', 'bb' => '0.7880', 'expected' => 62.40],
        ];

        foreach ($cases as $case) {
            $d = LevelingCalculationService::computeDistance($case['ba'], $case['bb']);
            $this->assertEqualsWithDelta(
                $case['expected'],
                (float) $d,
                self::DELTA_4,
                "Distance mismatch for BA={$case['ba']}, BB={$case['bb']}"
            );
        }
    }

    // ---------------------------------------------------------------------------
    // HI = Elevation_prev + BS
    // ---------------------------------------------------------------------------

    #[Test]
    public function hi_computed_from_bm_a_and_first_bs(): void
    {
        // HI(BM-A) = 100.0000 + 1.2100 = 101.2100
        $hi = LevelingCalculationService::computeHi('100.0000', '1.2100');
        $this->assertEqualsWithDelta(101.2100, (float) $hi, self::DELTA_4);
    }

    #[Test]
    public function hi_computed_for_all_canonical_bs_legs(): void
    {
        $cases = [
            ['elev' => '100.0000', 'bs' => '1.2100', 'expected' => 101.2100], // BM-A
            ['elev' => '100.0350', 'bs' => '1.3050', 'expected' => 101.3400], // TP-1
            ['elev' => '100.2580', 'bs' => '1.2430', 'expected' => 101.5010], // TP-2
        ];

        foreach ($cases as $case) {
            $hi = LevelingCalculationService::computeHi($case['elev'], $case['bs']);
            $this->assertEqualsWithDelta(
                $case['expected'],
                (float) $hi,
                self::DELTA_4,
                "HI mismatch for elev={$case['elev']}, bs={$case['bs']}"
            );
        }
    }

    // ---------------------------------------------------------------------------
    // Elevation_FS = HI - FS   |   Elevation_IS = HI - IS
    // ---------------------------------------------------------------------------

    #[Test]
    public function elevation_at_tp1_computed_correctly(): void
    {
        // Elev(TP-1) = 101.2100 - 1.1750 = 100.0350
        $elev = LevelingCalculationService::computeElevation('101.2100', '1.1750');
        $this->assertEqualsWithDelta(100.0350, (float) $elev, self::DELTA_4);
    }

    #[Test]
    public function elevation_at_tp2_computed_correctly(): void
    {
        // Elev(TP-2) = 101.3400 - 1.0820 = 100.2580
        $elev = LevelingCalculationService::computeElevation('101.3400', '1.0820');
        $this->assertEqualsWithDelta(100.2580, (float) $elev, self::DELTA_4);
    }

    #[Test]
    public function elevation_at_bm_b_computed_correctly(): void
    {
        // Elev(BM-B) = 101.5010 - 1.1000 = 100.4010
        $elev = LevelingCalculationService::computeElevation('101.5010', '1.1000');
        $this->assertEqualsWithDelta(100.4010, (float) $elev, self::DELTA_4);
    }

    #[Test]
    public function elevation_computation_uses_bcmath_not_float(): void
    {
        // Ensure precision is maintained — bcmath result must equal expected string value
        $elev = LevelingCalculationService::computeElevation('101.5010', '1.1000');
        $this->assertSame('100.4010', $elev);
    }

    // ---------------------------------------------------------------------------
    // Full pipeline: BM-A → TP-1 → TP-2 → BM-B
    // ---------------------------------------------------------------------------

    #[Test]
    public function full_pipeline_produces_correct_elevations_for_canonical_dataset(): void
    {
        $readings = $this->canonicalReadings();
        $results  = LevelingCalculationService::calculateElevationsStatic($readings, '100.0000');

        // Index by point name for easy assertion
        $byPoint = collect($results)->keyBy('point_name');

        // BM-A is the starting benchmark — elevation unchanged
        $this->assertEqualsWithDelta(100.0000, (float) $byPoint['BM-A']['raw_elevation'], self::DELTA_4);

        // Intermediate turning points
        $this->assertEqualsWithDelta(100.0350, (float) $byPoint['TP-1']['raw_elevation'], self::DELTA_4);
        $this->assertEqualsWithDelta(100.2580, (float) $byPoint['TP-2']['raw_elevation'], self::DELTA_4);
        $this->assertEqualsWithDelta(100.4010, (float) $byPoint['BM-B']['raw_elevation'], self::DELTA_4);
    }

    #[Test]
    public function full_pipeline_sets_hi_only_on_bs_rows(): void
    {
        $results = LevelingCalculationService::calculateElevationsStatic($this->canonicalReadings(), '100.0000');
        $bySeq   = collect($results)->keyBy('sequence_no');

        // Seq 1 (BS at BM-A) — HI must be set
        $this->assertEqualsWithDelta(101.2100, (float) $bySeq[1]['hi'], self::DELTA_4);

        // Seq 2 (FS at TP-1) — HI must be null
        $this->assertNull($bySeq[2]['hi']);

        // Seq 3 (BS at TP-1) — HI must be set
        $this->assertEqualsWithDelta(101.3400, (float) $bySeq[3]['hi'], self::DELTA_4);
    }

    #[Test]
    public function full_pipeline_initialises_correction_to_zero(): void
    {
        $results = LevelingCalculationService::calculateElevationsStatic($this->canonicalReadings(), '100.0000');

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(0.0, (float) $row['correction'], self::DELTA_6,
                "correction must be 0 on sequence_no={$row['sequence_no']}"
            );
        }
    }

    #[Test]
    public function full_pipeline_initialises_adjusted_elevation_equal_to_raw(): void
    {
        $results = LevelingCalculationService::calculateElevationsStatic($this->canonicalReadings(), '100.0000');

        foreach ($results as $row) {
            $this->assertEqualsWithDelta(
                (float) $row['raw_elevation'],
                (float) $row['adjusted_elevation'],
                self::DELTA_4,
                "adjusted_elevation must equal raw_elevation before adjustment"
            );
        }
    }

    #[Test]
    public function full_pipeline_processes_readings_in_sequence_no_order(): void
    {
        // Pass readings in reverse order — output must still be in sequence
        $shuffled = array_reverse($this->canonicalReadings());
        $results  = LevelingCalculationService::calculateElevationsStatic($shuffled, '100.0000');

        $sequences = array_column($results, 'sequence_no');
        $sorted    = $sequences;
        sort($sorted);

        $this->assertSame($sorted, $sequences);
    }

    #[Test]
    public function full_pipeline_cumulative_distance_is_monotonically_increasing(): void
    {
        $results = LevelingCalculationService::calculateElevationsStatic($this->canonicalReadings(), '100.0000');

        $prev = -1;
        foreach ($results as $row) {
            $this->assertGreaterThanOrEqual($prev, (float) $row['cumulative_distance']);
            $prev = (float) $row['cumulative_distance'];
        }
    }

    // ---------------------------------------------------------------------------
    // Idempotency
    // ---------------------------------------------------------------------------

    #[Test]
    public function pipeline_is_idempotent_when_run_multiple_times(): void
    {
        $readings = $this->canonicalReadings();

        $first  = LevelingCalculationService::calculateElevationsStatic($readings, '100.0000');
        $second = LevelingCalculationService::calculateElevationsStatic($readings, '100.0000');

        $this->assertEquals($first, $second);
    }

    // ---------------------------------------------------------------------------
    // Helper: canonical dataset
    // ---------------------------------------------------------------------------

    private function canonicalReadings(): array
    {
        return [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'reading_type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970', 'distance_m' => null],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'reading_type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630', 'distance_m' => null],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'reading_type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890', 'distance_m' => null],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'reading_type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700', 'distance_m' => null],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'reading_type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300', 'distance_m' => null],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'reading_type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880', 'distance_m' => null],
        ];
    }
}