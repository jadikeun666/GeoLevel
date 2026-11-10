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
}
