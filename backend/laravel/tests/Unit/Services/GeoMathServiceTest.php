<?php

namespace Tests\Unit\Services;

use App\Services\GeoMathService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Expected values are derived analytically from the haversine definition:
 * along the equator or a meridian the arc length is exactly R * delta_angle.
 */
class GeoMathServiceTest extends TestCase
{
    private GeoMathService $geo;
    private float $radius;

    protected function setUp(): void
    {
        parent::setUp();
        $this->geo    = new GeoMathService();
        $this->radius = (float) config('geolevel.outdoor.earth_radius_m');
    }

    #[Test]
    public function identical_points_have_zero_distance(): void
    {
        $this->assertSame(0.0, $this->geo->haversine(-6.2, 106.8, -6.2, 106.8));
    }

    #[Test]
    public function one_degree_of_longitude_on_equator_equals_radius_times_angle(): void
    {
        $expected = $this->radius * deg2rad(1);

        $this->assertEqualsWithDelta($expected, $this->geo->haversine(0, 100, 0, 101), 0.000001);
    }

    #[Test]
    public function one_degree_of_latitude_along_meridian_equals_radius_times_angle(): void
    {
        $expected = $this->radius * deg2rad(1);

        $this->assertEqualsWithDelta($expected, $this->geo->haversine(10, 106, 11, 106), 0.000001);
    }

    #[Test]
    public function distance_is_symmetric(): void
    {
        $ab = $this->geo->haversine(-6.2, 106.8, -7.25, 112.75);
        $ba = $this->geo->haversine(-7.25, 112.75, -6.2, 106.8);

        $this->assertEqualsWithDelta($ab, $ba, 0.000001);
    }

    #[Test]
    public function numeric_strings_from_database_are_accepted(): void
    {
        $fromFloats  = $this->geo->haversine(-6.2, 106.8, -6.3, 106.9);
        $fromStrings = $this->geo->haversine('-6.20000000', '106.80000000', '-6.30000000', '106.90000000');

        $this->assertEqualsWithDelta($fromFloats, $fromStrings, 0.000001);
    }
}
