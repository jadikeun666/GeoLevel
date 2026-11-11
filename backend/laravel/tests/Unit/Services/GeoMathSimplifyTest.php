<?php

namespace Tests\Unit\Services;

use App\Services\GeoMathService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Expectations follow from the definition: on the equator a step of 0.0001 deg
 * of latitude is R * deg2rad(0.0001) ~ 11.12 m of lateral deviation.
 */
class GeoMathSimplifyTest extends TestCase
{
    private GeoMathService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo = new GeoMathService();
    }

    #[Test]
    public function empty_and_tiny_inputs_are_returned_unchanged(): void
    {
        $this->assertSame([], $this->geo->simplify([], 2.0));
        $this->assertSame([0], $this->geo->simplify([[0, 0]], 2.0));
        $this->assertSame([0, 1], $this->geo->simplify([[0, 0], [0, 1]], 2.0));
    }

    #[Test]
    public function collinear_points_collapse_to_first_and_last(): void
    {
        $coords = [[0, 0], [0, 0.001], [0, 0.002], [0, 0.003], [0, 0.004]];

        $this->assertSame([0, 4], $this->geo->simplify($coords, 2.0));
    }

    #[Test]
    public function deviation_above_tolerance_is_kept_and_below_is_dropped(): void
    {
        // Middle point deviates ~11.12 m from the straight line.
        $coords = [[0, 0], [0.0001, 0.001], [0, 0.002]];

        $this->assertSame([0, 1, 2], $this->geo->simplify($coords, 5.0));
        $this->assertSame([0, 2], $this->geo->simplify($coords, 15.0));
    }

    #[Test]
    public function zero_tolerance_keeps_every_point(): void
    {
        $coords = [[0, 0], [0, 0.001], [0, 0.002], [0, 0.003]];

        $this->assertSame([0, 1, 2, 3], $this->geo->simplify($coords, 0.0));
    }

    #[Test]
    public function kept_indices_are_ascending_and_include_both_endpoints(): void
    {
        $coords = [[0, 0], [0.0003, 0.001], [0, 0.002], [0.0003, 0.003], [0, 0.004], [0.0003, 0.005]];

        $kept = $this->geo->simplify($coords, 5.0);

        $this->assertSame(0, $kept[0]);
        $this->assertSame(5, $kept[array_key_last($kept)]);
        $sorted = $kept;
        sort($sorted);
        $this->assertSame($sorted, $kept);
    }

    #[Test]
    public function very_long_collinear_track_does_not_overflow_the_stack(): void
    {
        $coords = [];
        for ($i = 0; $i < 20000; $i++) {
            $coords[] = [0, $i * 0.00001];
        }

        $this->assertSame([0, 19999], $this->geo->simplify($coords, 2.0));
    }
}
