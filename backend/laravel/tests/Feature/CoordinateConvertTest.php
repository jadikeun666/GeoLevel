<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinateConvertTest extends TestCase
{
    use RefreshDatabase;

    private function convert(array $body)
    {
        return $this->actingAs(User::factory()->create())->postJson(route('outdoor.convert'), $body);
    }

    public function test_lat_lng_input_returns_all_formats_matching_proj_reference(): void
    {
        $r = $this->convert(['lat' => -6.2088, 'lng' => 106.8456])->assertOk();

        $this->assertSame('48MYU0420713358', $r->json('data.mgrs'));
        $this->assertSame(48, $r->json('data.utm.zone'));
        $this->assertSame('S', $r->json('data.utm.hemisphere'));
        $this->assertEqualsWithDelta(704207.169, (float) $r->json('data.utm.easting'), 0.001);
        $this->assertEqualsWithDelta(9313358.339, (float) $r->json('data.utm.northing'), 0.001);
        $this->assertSame('6°12\'31.68"S', $r->json('data.dms.lat'));
        $this->assertSame('106°50\'44.16"E', $r->json('data.dms.lng'));
    }

    public function test_utm_input_round_trips_to_lat_lng(): void
    {
        $r = $this->convert(['zone' => 48, 'hemisphere' => 'S', 'easting' => 704207.169, 'northing' => 9313358.339])->assertOk();

        $this->assertEqualsWithDelta(-6.2088, (float) $r->json('data.lat'), 2e-8);
        $this->assertEqualsWithDelta(106.8456, (float) $r->json('data.lng'), 2e-8);
    }

    public function test_mgrs_is_null_outside_its_latitude_range(): void
    {
        $this->convert(['lat' => 84.5, 'lng' => 100.0])->assertOk()->assertJsonPath('data.mgrs', null);
    }

    public function test_zone_outside_indonesia_is_rejected_and_input_is_required(): void
    {
        $this->convert(['lat' => 0, 'lng' => 144.5])->assertStatus(422)->assertJsonValidationErrors('lng');
        $this->convert(['zone' => 55, 'hemisphere' => 'N', 'easting' => 500000, 'northing' => 0])->assertStatus(422)->assertJsonValidationErrors('zone');
        $this->convert([])->assertStatus(422);
    }

    public function test_guests_are_rejected(): void
    {
        $this->postJson(route('outdoor.convert'), ['lat' => 0, 'lng' => 111])->assertUnauthorized();
    }
}
