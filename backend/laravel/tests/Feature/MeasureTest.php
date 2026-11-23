<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeoMathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasureTest extends TestCase
{
    use RefreshDatabase;

    private function measure(array $body)
    {
        return $this->actingAs(User::factory()->create())->postJson(route('outdoor.measure'), $body);
    }

    public function test_line_distance_of_one_degree_of_latitude(): void
    {
        $r = $this->measure(['mode' => 'line', 'points' => [[0, 0], [1, 0]]])->assertOk();

        $this->assertEqualsWithDelta(111195.080, (float) $r->json('data.distance_m'), 0.001);
        $this->assertNull($r->json('data.area_m2'));
    }

    public function test_area_of_a_100_by_200_m_utm_rectangle_is_20000(): void
    {
        $geo = new GeoMathService();
        $points = array_map(function (array $c) use ($geo) {
            $p = $geo->fromUtm(48, 'S', $c[0], $c[1]);
            return [(float) $p['lat'], (float) $p['lng']];
        }, [[500000, 9300000], [500100, 9300000], [500100, 9300200], [500000, 9300200]]);

        $r = $this->measure(['mode' => 'area', 'points' => $points])->assertOk();

        $this->assertEqualsWithDelta(20000.0, (float) $r->json('data.area_m2'), 2.0);
        $this->assertEqualsWithDelta(600.0, (float) $r->json('data.perimeter_m'), 5.0);
    }

    public function test_area_across_the_equator_is_continuous(): void
    {
        $geo = new GeoMathService();
        $points = array_map(function (array $c) use ($geo) {
            $p = $geo->fromUtm(49, $c[2], $c[0], $c[1]);
            return [(float) $p['lat'], (float) $p['lng']];
        }, [[500000, 9999900, 'S'], [500100, 9999900, 'S'], [500100, 100, 'N'], [500000, 100, 'N']]);

        $r = $this->measure(['mode' => 'area', 'points' => $points])->assertOk();

        $this->assertEqualsWithDelta(100 * 200, (float) $r->json('data.area_m2'), 2.0);
    }

    public function test_area_spanning_two_zones_is_rejected_but_line_is_fine(): void
    {
        $points = [[0.0, 107.9], [0.1, 108.1], [0.0, 108.1]]; // zone 48 and zone 49

        $this->measure(['mode' => 'area', 'points' => $points])->assertStatus(422)->assertJsonValidationErrors('points');
        $this->measure(['mode' => 'line', 'points' => $points])->assertOk();
    }

    public function test_minimum_points_per_mode(): void
    {
        $this->measure(['mode' => 'line', 'points' => [[0, 0]]])->assertStatus(422);
        $this->measure(['mode' => 'area', 'points' => [[0, 0], [1, 1]]])->assertStatus(422);
    }
}
