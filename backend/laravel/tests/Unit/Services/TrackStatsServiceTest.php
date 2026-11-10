<?php

namespace Tests\Unit\Services;

use App\Services\GeoMathService;
use App\Services\TrackStatsService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Synthetic data on the equator: a step of 0.001 deg of longitude is exactly
 * L = R * deg2rad(0.001) meters, so every expectation is hand-computable.
 */
class TrackStatsServiceTest extends TestCase
{
    private TrackStatsService $stats;
    private float $step;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stats = new TrackStatsService(new GeoMathService());
        $this->step  = (float) config('geolevel.outdoor.earth_radius_m') * deg2rad(0.001);

        config([
            'geolevel.outdoor.stats_elevation_noise_threshold_m' => 3.0,
            'geolevel.outdoor.stats_moving_speed_min_mps'        => 0.3,
        ]);
    }

    private function pt(float $lng, ?string $time = null, string|float|null $ele = null): array
    {
        return [
            'lat'         => 0.0,
            'lng'         => $lng,
            'ele'         => $ele,
            'recorded_at' => $time === null ? null : Carbon::parse($time),
        ];
    }

    #[Test]
    public function empty_input_returns_zero_count_and_null_stats(): void
    {
        $result = $this->stats->fromPoints([]);

        $this->assertSame(0, $result['point_count']);
        $this->assertNull($result['distance_m']);
        $this->assertNull($result['duration_s']);
    }

    #[Test]
    public function single_point_has_zero_distance_and_duration(): void
    {
        $result = $this->stats->fromPoints([$this->pt(0.0, '2026-01-01 00:00:00')]);

        $this->assertSame(1, $result['point_count']);
        $this->assertSame('0.000', $result['distance_m']);
        $this->assertSame(0, $result['duration_s']);
        $this->assertSame(0, $result['moving_time_s']);
    }

    #[Test]
    public function distance_is_sum_of_segments_rounded_to_three_decimals(): void
    {
        $result = $this->stats->fromPoints([
            $this->pt(0.000),
            $this->pt(0.001),
            $this->pt(0.002),
        ]);

        $this->assertEqualsWithDelta(2 * $this->step, (float) $result['distance_m'], 0.0006);
        $this->assertMatchesRegularExpression('/^\d+\.\d{3}$/', $result['distance_m']);
    }

    #[Test]
    public function duration_and_moving_time_for_continuous_movement(): void
    {
        $result = $this->stats->fromPoints([
            $this->pt(0.000, '2026-01-01 00:00:00'),
            $this->pt(0.001, '2026-01-01 00:00:10'),
            $this->pt(0.002, '2026-01-01 00:00:20'),
        ]);

        $this->assertSame(20, $result['duration_s']);
        $this->assertSame(20, $result['moving_time_s']);
        $this->assertSame('2026-01-01 00:00:00', $result['started_at']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-01 00:00:20', $result['ended_at']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function stationary_segment_is_excluded_from_moving_time(): void
    {
        $result = $this->stats->fromPoints([
            $this->pt(0.000, '2026-01-01 00:00:00'),
            $this->pt(0.001, '2026-01-01 00:00:10'),
            $this->pt(0.001, '2026-01-01 00:01:10'),
            $this->pt(0.002, '2026-01-01 00:01:20'),
        ]);

        $this->assertSame(80, $result['duration_s']);
        $this->assertSame(20, $result['moving_time_s']);
    }

    #[Test]
    public function elevation_gain_and_loss_apply_noise_threshold_with_string_inputs(): void
    {
        // ref=100; 101 (+1) and 102 (+2) ignored; 103.5 (+3.5) counted, ref=103.5;
        // 100.5 (-3.0) counted as loss (threshold inclusive), ref=100.5.
        $result = $this->stats->fromPoints([
            $this->pt(0.000, null, '100.0000'),
            $this->pt(0.001, null, '101.0000'),
            $this->pt(0.002, null, '102.0000'),
            $this->pt(0.003, null, '103.5000'),
            $this->pt(0.004, null, '100.5000'),
        ]);

        $this->assertSame('3.500', $result['elevation_gain_m']);
        $this->assertSame('3.000', $result['elevation_loss_m']);
        $this->assertSame('100.0000', $result['min_elevation']);
        $this->assertSame('103.5000', $result['max_elevation']);
    }

    #[Test]
    public function elevation_stats_are_null_when_no_point_has_elevation(): void
    {
        $result = $this->stats->fromPoints([$this->pt(0.000), $this->pt(0.001)]);

        $this->assertNull($result['elevation_gain_m']);
        $this->assertNull($result['elevation_loss_m']);
        $this->assertNull($result['min_elevation']);
        $this->assertNull($result['max_elevation']);
    }

    #[Test]
    public function time_based_stats_are_null_when_any_timestamp_is_missing(): void
    {
        $result = $this->stats->fromPoints([
            $this->pt(0.000, '2026-01-01 00:00:00'),
            $this->pt(0.001, null),
        ]);

        $this->assertNull($result['duration_s']);
        $this->assertNull($result['moving_time_s']);
        $this->assertNull($result['started_at']);
        $this->assertNotNull($result['distance_m']);
    }

    #[Test]
    public function accepts_any_iterable_such_as_a_generator(): void
    {
        $generator = (function () {
            yield $this->pt(0.000, '2026-01-01 00:00:00');
            yield $this->pt(0.001, '2026-01-01 00:00:10');
        })();

        $result = $this->stats->fromPoints($generator);

        $this->assertSame(2, $result['point_count']);
        $this->assertSame(10, $result['duration_s']);
    }
}
