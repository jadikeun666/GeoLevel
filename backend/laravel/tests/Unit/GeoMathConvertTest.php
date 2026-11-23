<?php

namespace Tests\Unit;

use App\Services\GeoMathService;
use InvalidArgumentException;
use Tests\TestCase;

class GeoMathConvertTest extends TestCase
{
    // Reference values generated with PROJ (pyproj, EPSG:326xx/327xx) and GeoTrans (mgrs), 2026-11-23.
    // [name, lat, lng, zone, hemisphere, easting, northing, mgrs]
    private const POINTS = [
        ['Banda Aceh',     5.5483,  95.3238, 46, 'N', 757447.111,  613778.047, '46NGM5744713778'],
        ['Medan',          3.5952,  98.6722, 47, 'N', 463595.175,  397389.385, '47NMD6359597389'],
        ['Bandar Lampung', -5.4294, 105.2610, 48, 'S', 528913.432, 9399863.931, '48MWU2891399863'],
        ['Jakarta',        -6.2088, 106.8456, 48, 'S', 704207.169, 9313358.339, '48MYU0420713358'],
        ['Equator CM z49', 0.0,     111.0,   49, 'N', 500000.000,       0.000, '49NEA0000000000'],
        ['South of equator', -0.0001, 111.0, 49, 'S', 500000.000, 9999988.947, '49MEV0000099988'],
        ['Makassar',       -5.1477, 119.4327, 50, 'S', 769694.338, 9430495.194, '50MQV6969430495'],
        ['Manado',         1.4748,  124.8421, 51, 'N', 704947.664,  163094.890, '51NYB0494763094'],
        ['Jayapura',       -2.5337, 140.7181, 54, 'S', 468661.922, 9719944.782, '54MVC6866119944'],
    ];

    public function test_to_utm_and_mgrs_match_reference_values(): void
    {
        $geo = new GeoMathService();

        foreach (self::POINTS as [$name, $lat, $lng, $zone, $hemi, $e, $n, $mgrs]) {
            $utm = $geo->toUtm($lat, $lng);
            $this->assertSame($zone, $utm['zone'], $name);
            $this->assertSame($hemi, $utm['hemisphere'], $name);
            $this->assertEqualsWithDelta($e, (float) $utm['easting'], 0.001, "{$name} easting");
            $this->assertEqualsWithDelta($n, (float) $utm['northing'], 0.001, "{$name} northing");
            $this->assertSame($mgrs, $geo->toMgrs($lat, $lng), "{$name} mgrs");
        }
    }

    public function test_from_utm_round_trips_within_a_millimeter(): void
    {
        $geo = new GeoMathService();

        foreach (self::POINTS as [$name, $lat, $lng, $zone, $hemi, $e, $n]) {
            $back = $geo->fromUtm($zone, $hemi, $e, $n);
            $this->assertEqualsWithDelta($lat, (float) $back['lat'], 2e-8, "{$name} lat");
            $this->assertEqualsWithDelta($lng, (float) $back['lng'], 2e-8, "{$name} lng");
        }
    }

    public function test_zones_outside_46_to_54_are_rejected(): void
    {
        $geo = new GeoMathService();

        foreach ([89.9, 144.5] as $lng) { // zone 45 and zone 55
            try {
                $geo->toUtm(0.0, $lng);
                $this->fail("lng {$lng} should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_to_dms_matches_hand_computed_values(): void
    {
        $geo = new GeoMathService();

        $this->assertSame('6°12\'31.68"S', $geo->toDms(-6.2088, true));
        $this->assertSame('106°50\'44.16"E', $geo->toDms(106.8456, false));
        $this->assertSame('0°00\'00.00"N', $geo->toDms(0, true));
    }
}
