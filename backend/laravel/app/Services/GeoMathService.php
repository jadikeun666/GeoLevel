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

    private const MGRS_BANDS   = 'CDEFGHJKLMNPQRSTUVWX';
    private const MGRS_COLUMNS = ['ABCDEFGH', 'JKLMNPQR', 'STUVWXYZ'];
    private const MGRS_ROWS    = 'ABCDEFGHJKLMNPQRSTUV';

    /**
     * WGS84 -> UTM (Krüger n-series, 4th order; sub-millimeter inside a zone).
     * Zones limited to config utm_zone_min..max (Indonesia).
     *
     * @return array{zone: int, hemisphere: string, easting: string, northing: string}
     */
    public function toUtm(float|string $lat, float|string $lng): array
    {
        $lat  = (float) $lat;
        $lng  = (float) $lng;
        $zone = (int) floor(($lng + 180) / 6) + 1;
        $this->assertZone($zone);

        $k   = $this->kruger();
        $phi = deg2rad($lat);
        $lam = deg2rad($lng - ($zone * 6 - 183));
        $q   = 2 * sqrt($k['n']) / (1 + $k['n']);
        $t   = sinh(atanh(sin($phi)) - $q * atanh($q * sin($phi)));
        $xi  = atan2($t, cos($lam));
        $eta = atanh(sin($lam) / sqrt(1 + $t * $t));

        $sumE = 0.0;
        $sumN = 0.0;
        foreach ($k['alpha'] as $i => $alpha) {
            $j     = 2 * ($i + 1);
            $sumE += $alpha * cos($j * $xi) * sinh($j * $eta);
            $sumN += $alpha * sin($j * $xi) * cosh($j * $eta);
        }

        $scale = (float) config('geolevel.outdoor.utm_scale_factor') * $k['A'];
        $north = $lat >= 0;

        return [
            'zone'       => $zone,
            'hemisphere' => $north ? 'N' : 'S',
            'easting'    => number_format((float) config('geolevel.outdoor.utm_central_meridian_easting') + $scale * ($eta + $sumE), 3, '.', ''),
            'northing'   => number_format(($north ? 0.0 : (float) config('geolevel.outdoor.utm_false_northing_south')) + $scale * ($xi + $sumN), 3, '.', ''),
        ];
    }

    /**
     * UTM -> WGS84 (inverse of toUtm).
     *
     * @return array{lat: string, lng: string}  8 decimals, NUMERIC-ready
     */
    public function fromUtm(int $zone, string $hemisphere, float|string $easting, float|string $northing): array
    {
        $this->assertZone($zone);
        if (! in_array($hemisphere, ['N', 'S'], true)) {
            throw new \InvalidArgumentException('Hemisphere must be N or S.');
        }

        $k     = $this->kruger();
        $scale = (float) config('geolevel.outdoor.utm_scale_factor') * $k['A'];
        $xi    = ((float) $northing - ($hemisphere === 'S' ? (float) config('geolevel.outdoor.utm_false_northing_south') : 0.0)) / $scale;
        $eta   = ((float) $easting - (float) config('geolevel.outdoor.utm_central_meridian_easting')) / $scale;

        $xiP  = $xi;
        $etaP = $eta;
        foreach ($k['beta'] as $i => $beta) {
            $j     = 2 * ($i + 1);
            $xiP  -= $beta * sin($j * $xi) * cosh($j * $eta);
            $etaP -= $beta * cos($j * $xi) * sinh($j * $eta);
        }

        $chi = asin(sin($xiP) / cosh($etaP));
        $phi = $chi;
        foreach ($k['delta'] as $i => $delta) {
            $phi += $delta * sin(2 * ($i + 1) * $chi);
        }
        $lam = atan2(sinh($etaP), cos($xiP));

        return [
            'lat' => number_format(rad2deg($phi), 8, '.', ''),
            'lng' => number_format($zone * 6 - 183 + rad2deg($lam), 8, '.', ''),
        ];
    }

    /**
     * WGS84 -> MGRS, 1 m precision, e.g. 48MYU0420713358.
     * shortcut: truncates the already mm-rounded UTM value, so a point within 0.5 mm of a 1 m boundary can land one cell over; upgrade if sub-meter MGRS is ever needed.
     */
    public function toMgrs(float|string $lat, float|string $lng): string
    {
        $latF = (float) $lat;
        if ($latF < -80 || $latF >= 84) {
            throw new \InvalidArgumentException('MGRS latitude must be within -80..84.');
        }

        $utm  = $this->toUtm($lat, $lng);
        $e    = (int) floor((float) $utm['easting']);
        $n    = (int) floor((float) $utm['northing']);
        $zone = $utm['zone'];

        $band = self::MGRS_BANDS[(int) min(19, floor(($latF + 80) / 8))];
        $col  = self::MGRS_COLUMNS[($zone - 1) % 3][intdiv($e, 100000) - 1];
        $row  = self::MGRS_ROWS[(intdiv($n, 100000) + ($zone % 2 === 0 ? 5 : 0)) % 20];

        return sprintf('%d%s%s%s%05d%05d', $zone, $band, $col, $row, $e % 100000, $n % 100000);
    }

    /** Degrees -> DMS string with 0.01" resolution, e.g. 6°12'31.68"S. */
    public function toDms(float|string $degrees, bool $isLatitude): string
    {
        $v     = (float) $degrees;
        $hemi  = $isLatitude ? ($v >= 0 ? 'N' : 'S') : ($v >= 0 ? 'E' : 'W');
        $cs    = (int) round(abs($v) * 360000); // hundredths of an arc-second; split after rounding so 59.995" carries correctly

        return sprintf('%d°%02d\'%05.2f"%s', intdiv($cs, 360000), intdiv($cs % 360000, 6000), ($cs % 6000) / 100, $hemi);
    }

    /**
     * Path length and (optionally) UTM-grid polygon area for [lat, lng] points.
     * shortcut: area is grid area (no k0 scale correction, ~0.08% near the central meridian); add it if cadastral accuracy is ever needed.
     *
     * @param  list<array{0: float|string, 1: float|string}>  $points
     * @return array{distance_m: string, perimeter_m: ?string, area_m2: ?string}
     * @throws \InvalidArgumentException when area points span more than one zone or leave zones 46..54
     */
    public function measure(array $points, bool $area): array
    {
        $n    = count($points);
        $path = 0.0;
        for ($i = 1; $i < $n; $i++) {
            $path += $this->haversine($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
        }

        if (! $area) {
            return ['distance_m' => number_format($path, 3, '.', ''), 'perimeter_m' => null, 'area_m2' => null];
        }

        $perimeter = $path + $this->haversine($points[$n - 1][0], $points[$n - 1][1], $points[0][0], $points[0][1]);
        $falseN    = (float) config('geolevel.outdoor.utm_false_northing_south');
        $zone      = null;
        $xs        = [];
        $ys        = [];
        foreach ($points as $point) {
            $utm    = $this->toUtm($point[0], $point[1]);
            $zone ??= $utm['zone'];
            if ($utm['zone'] !== $zone) {
                throw new \InvalidArgumentException('Points span more than one UTM zone.');
            }
            $xs[] = (float) $utm['easting'];
            $ys[] = (float) $utm['northing'] - ($utm['hemisphere'] === 'S' ? $falseN : 0.0);
        }

        // Shift to the first vertex so the cross products stay small (float precision).
        $x0  = $xs[0];
        $y0  = $ys[0];
        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j    = ($i + 1) % $n;
            $sum += ($xs[$i] - $x0) * ($ys[$j] - $y0) - ($xs[$j] - $x0) * ($ys[$i] - $y0);
        }

        return [
            'distance_m'  => number_format($path, 3, '.', ''),
            'perimeter_m' => number_format($perimeter, 3, '.', ''),
            'area_m2'     => number_format(abs($sum) / 2, 3, '.', ''),
        ];
    }

    private function assertZone(int $zone): void
    {
        if ($zone < (int) config('geolevel.outdoor.utm_zone_min') || $zone > (int) config('geolevel.outdoor.utm_zone_max')) {
            throw new \InvalidArgumentException("UTM zone {$zone} is outside the supported range.");
        }
    }

    /** Krüger series coefficients for WGS84 (n^4 order). */
    private function kruger(): array
    {
        $f  = 1 / (float) config('geolevel.outdoor.wgs84_inv_f');
        $a  = (float) config('geolevel.outdoor.wgs84_a');
        $n  = $f / (2 - $f);
        $n2 = $n * $n;
        $n3 = $n2 * $n;
        $n4 = $n3 * $n;

        return [
            'n'     => $n,
            'A'     => $a / (1 + $n) * (1 + $n2 / 4 + $n4 / 64 + $n3 * $n3 / 256),
            'alpha' => [
                $n / 2 - 2 * $n2 / 3 + 5 * $n3 / 16 + 41 * $n4 / 180,
                13 * $n2 / 48 - 3 * $n3 / 5 + 557 * $n4 / 1440,
                61 * $n3 / 240 - 103 * $n4 / 140,
                49561 * $n4 / 161280,
            ],
            'beta'  => [
                $n / 2 - 2 * $n2 / 3 + 37 * $n3 / 96 - $n4 / 360,
                $n2 / 48 + $n3 / 15 - 437 * $n4 / 1440,
                17 * $n3 / 480 - 37 * $n4 / 840,
                4397 * $n4 / 161280,
            ],
            'delta' => [
                2 * $n - 2 * $n2 / 3 - 2 * $n3 + 116 * $n4 / 45,
                7 * $n2 / 3 - 8 * $n3 / 5 - 227 * $n4 / 45,
                56 * $n3 / 15 - 136 * $n4 / 35,
                4279 * $n4 / 630,
            ],
        ];
    }
}
