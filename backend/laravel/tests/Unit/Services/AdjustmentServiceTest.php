<?php

namespace Tests\Unit\Services;
use PHPUnit\Framework\Attributes\Test;

use App\Services\AdjustmentService;
use Tests\TestCase;

/**
 * Unit tests for AdjustmentService.
 */
class AdjustmentServiceTest extends TestCase
{
    private const DELTA_6 = 0.000001;
    private const DELTA_4 = 0.0001;

    #[Test]
    public function equal_correction_per_point_matches_canonical_value(): void
    {
        $correction = AdjustmentService::equalCorrectionPerPoint('0.401000', 3);
        $this->assertEqualsWithDelta(-0.133667, (float) $correction, self::DELTA_6);
    }

    #[Test]
    public function equal_distribution_adjusted_elevation_tp1(): void
    {
        $adjusted = AdjustmentService::applyCorrection('100.0350', '-0.133667');
        $this->assertEqualsWithDelta(99.901333, (float) $adjusted, self::DELTA_6);
    }

    #[Test]
    public function equal_distribution_adjusted_elevation_tp2(): void
    {
        $adjusted = AdjustmentService::applyCorrection('100.2580', '-0.267333');
        $this->assertEqualsWithDelta(99.990667, (float) $adjusted, self::DELTA_6);
    }

    #[Test]
    public function equal_distribution_adjusted_elevation_bm_b(): void
    {
        $adjusted = AdjustmentService::applyCorrection('100.4010', '-0.401000');
        $this->assertEqualsWithDelta(100.000000, (float) $adjusted, self::DELTA_6);
    }

    #[Test]
    public function equal_distribution_full_run_canonical_dataset(): void
    {
        $computedElevations = $this->canonicalComputedElevations();
        $results = AdjustmentService::adjust($computedElevations, '0.401000', 'equal');
        $byPoint = collect($results)->keyBy('point_name');

        $this->assertEqualsWithDelta(99.901333,  (float) $byPoint['TP-1']['adjusted_elevation'], self::DELTA_6);
        $this->assertEqualsWithDelta(99.990667,  (float) $byPoint['TP-2']['adjusted_elevation'], self::DELTA_6);
        $this->assertEqualsWithDelta(100.000000, (float) $byPoint['BM-B']['adjusted_elevation'], self::DELTA_6);
    }

    #[Test]
    public function equal_distribution_corrections_sum_to_negative_fh(): void
    {
        $computedElevations = $this->canonicalComputedElevations();
        $results = AdjustmentService::adjust($computedElevations, '0.401000', 'equal');

        $sumCorrections = array_reduce($results, function ($carry, $row) {
            return bcadd($carry, $row['correction'], 10);
        }, '0');

        $this->assertEqualsWithDelta(-0.802000, (float) $sumCorrections, self::DELTA_6);
    }

    #[Test]
    public function bowditch_correction_proportional_to_cumulative_distance(): void
    {
        $correction = AdjustmentService::bowditchCorrection('0.401000', '125.2', '375.6');
        $this->assertEqualsWithDelta(-0.133600, (float) $correction, self::DELTA_4);
    }

    #[Test]
    public function bowditch_correction_at_full_distance_equals_negative_fh(): void
    {
        $correction = AdjustmentService::bowditchCorrection('0.401000', '375.6', '375.6');
        $this->assertEqualsWithDelta(-0.401000, (float) $correction, self::DELTA_6);
    }

    #[Test]
    public function bowditch_correction_at_zero_distance_is_zero(): void
    {
        $correction = AdjustmentService::bowditchCorrection('0.401000', '0', '375.6');
        $this->assertEqualsWithDelta(0.0, (float) $correction, self::DELTA_6);
    }

    #[Test]
    public function setting_correction_to_zero_restores_raw_elevation(): void
    {
        $raw      = '100.0350';
        $adjusted = AdjustmentService::applyCorrection($raw, '-0.133667');
        $restored = AdjustmentService::applyCorrection($raw, '0');

        $this->assertSame($raw, $restored);
    }

    #[Test]
    public function adjust_method_preserves_raw_elevation_column(): void
    {
        $results = AdjustmentService::adjust(
            $this->canonicalComputedElevations(),
            '0.401000',
            'equal'
        );
        $byPoint = collect($results)->keyBy('point_name');

        $this->assertEqualsWithDelta(100.0350, (float) $byPoint['TP-1']['raw_elevation'], self::DELTA_4);
        $this->assertEqualsWithDelta(100.2580, (float) $byPoint['TP-2']['raw_elevation'], self::DELTA_4);
        $this->assertEqualsWithDelta(100.4010, (float) $byPoint['BM-B']['raw_elevation'], self::DELTA_4);
    }

    #[Test]
    public function adjust_method_never_mutates_raw_readings(): void
    {
        $original = $this->canonicalComputedElevations();
        $snapshot = json_decode(json_encode($original), true);

        AdjustmentService::adjust($original, '0.401000', 'equal');

        $this->assertEquals($snapshot, $original);
    }

    private function canonicalComputedElevations(): array
    {
        return [
            [
                'sequence_no'         => 1,
                'point_name'          => 'BM-A',
                'reading_type'        => 'BS',
                'raw_elevation'       => '100.0000',
                'correction'          => '0',
                'adjusted_elevation'  => '100.0000',
                'cumulative_distance' => '0',
            ],
            [
                'sequence_no'         => 2,
                'point_name'          => 'TP-1',
                'reading_type'        => 'FS',
                'raw_elevation'       => '100.0350',
                'correction'          => '0',
                'adjusted_elevation'  => '100.0350',
                'cumulative_distance' => '125.2',
            ],
            [
                'sequence_no'         => 4,
                'point_name'          => 'TP-2',
                'reading_type'        => 'FS',
                'raw_elevation'       => '100.2580',
                'correction'          => '0',
                'adjusted_elevation'  => '100.2580',
                'cumulative_distance' => '250.4',
            ],
            [
                'sequence_no'         => 6,
                'point_name'          => 'BM-B',
                'reading_type'        => 'FS',
                'raw_elevation'       => '100.4010',
                'correction'          => '0',
                'adjusted_elevation'  => '100.4010',
                'cumulative_distance' => '375.6',
            ],
        ];
    }
}
