<?php

namespace App\Services;

/**
 * Geometry helpers for Outdoor features.
 *
 * Precision exception (docs/outdoor.md section 4): float is allowed INSIDE
 * these functions only. Inputs/outputs persisted to the DB stay NUMERIC.
 * Spherical model: for navigation, NOT for cadastral/legal use.
 */
class GeoMathService
{
    /**
     * Great-circle distance in meters between two WGS84 points (haversine).
     *
     * @param float|string $lat1 degrees (NUMERIC strings from DB accepted)
     */
    public function haversine(
        float|string $lat1,
        float|string $lng1,
        float|string $lat2,
        float|string $lng2,
    ): float {
        $radius = (float) config('geolevel.outdoor.earth_radius_m');

        $phi1 = deg2rad((float) $lat1);
        $phi2 = deg2rad((float) $lat2);
        $dPhi = $phi2 - $phi1;
        $dLam = deg2rad((float) $lng2 - (float) $lng1);

        $a = sin($dPhi / 2) ** 2
            + cos($phi1) * cos($phi2) * sin($dLam / 2) ** 2;

        return 2 * $radius * asin(min(1.0, sqrt($a)));
    }

    /**
     * Ramer-Douglas-Peucker simplification (iterative, no recursion).
     * Points are projected to a local equirectangular plane (meters) around
     * the mean latitude; distance is point-to-segment. First and last points
     * are always kept. Display use only: raw points are never modified.
     *
     * @param  list<array{0: float|string, 1: float|string}>  $coords  [lat, lng] pairs
     * @return list<int>  ascending indices of the points to keep
     */
    public function simplify(array $coords, float $toleranceM): array
    {
        $n = count($coords);
        if ($n === 0) {
            return [];
        }
        if ($n <= 2 || $toleranceM <= 0) {
            return range(0, $n - 1);
        }

        $radius  = (float) config('geolevel.outdoor.earth_radius_m');
        $meanLat = 0.0;
        foreach ($coords as $coord) {
            $meanLat += (float) $coord[0];
        }
        $cosLat = cos(deg2rad($meanLat / $n));

        $xs = [];
        $ys = [];
        foreach ($coords as $i => $coord) {
            $xs[$i] = $radius * deg2rad((float) $coord[1]) * $cosLat;
            $ys[$i] = $radius * deg2rad((float) $coord[0]);
        }

        $keep           = array_fill(0, $n, false);
        $keep[0]        = true;
        $keep[$n - 1]   = true;
        $stack          = [[0, $n - 1]];

        while ($stack !== []) {
            [$start, $end] = array_pop($stack);
            if ($end - $start < 2) {
                continue;
            }

            $ax   = $xs[$start];
            $ay   = $ys[$start];
            $dx   = $xs[$end] - $ax;
            $dy   = $ys[$end] - $ay;
            $len2 = $dx * $dx + $dy * $dy;

            $maxDist = -1.0;
            $maxIdx  = $start;
            for ($i = $start + 1; $i < $end; $i++) {
                $px = $xs[$i] - $ax;
                $py = $ys[$i] - $ay;
                if ($len2 == 0.0) {
                    $dist = hypot($px, $py);
                } else {
                    $t    = max(0.0, min(1.0, ($px * $dx + $py * $dy) / $len2));
                    $dist = hypot($px - $t * $dx, $py - $t * $dy);
                }
                if ($dist > $maxDist) {
                    $maxDist = $dist;
                    $maxIdx  = $i;
                }
            }

            if ($maxDist > $toleranceM) {
                $keep[$maxIdx] = true;
                $stack[]       = [$start, $maxIdx];
                $stack[]       = [$maxIdx, $end];
            }
        }

        return array_keys(array_filter($keep));
    }
}
