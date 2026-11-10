<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Computes track statistics in ONE ordered pass over the points.
 *
 * Input: iterable of arrays/objects with lat, lng, ele (nullable),
 * recorded_at (nullable), ordered by seq. Works with parsed GPX arrays
 * or a lazy DB cursor. Floats are used only inside the computation;
 * results are returned as NUMERIC-compatible strings.
 */
class TrackStatsService
{
    public function __construct(private readonly GeoMathService $geo)
    {
    }

    public function fromPoints(iterable $points): array
    {
        $threshold = (float) config('geolevel.outdoor.stats_elevation_noise_threshold_m');
        $minSpeed  = (float) config('geolevel.outdoor.stats_moving_speed_min_mps');

        $count    = 0;
        $distance = 0.0;
        $moving   = 0.0;
        $gain     = 0.0;
        $loss     = 0.0;
        $minEle   = null;
        $maxEle   = null;
        $refEle   = null;
        $hasTimes = true;
        $first    = null;
        $last     = null;
        $prevLat  = null;
        $prevLng  = null;
        $prevTime = null;

        foreach ($points as $point) {
            $lat  = data_get($point, 'lat');
            $lng  = data_get($point, 'lng');
            $raw  = data_get($point, 'recorded_at');
            $time = $raw === null ? null : Carbon::parse($raw);
            $eleRaw = data_get($point, 'ele');
            $ele    = $eleRaw === null ? null : (float) $eleRaw;

            $count++;
            if ($count === 1) {
                $first = $time;
            }
            $last = $time;
            if ($time === null) {
                $hasTimes = false;
            }

            if ($prevLat !== null) {
                $segment   = $this->geo->haversine($prevLat, $prevLng, $lat, $lng);
                $distance += $segment;

                if ($time !== null && $prevTime !== null) {
                    $dt = ($time->getPreciseTimestamp(3) - $prevTime->getPreciseTimestamp(3)) / 1000;
                    if ($dt > 0 && ($segment / $dt) >= $minSpeed) {
                        $moving += $dt;
                    }
                }
            }

            if ($ele !== null) {
                $minEle = $minEle === null ? $ele : min($minEle, $ele);
                $maxEle = $maxEle === null ? $ele : max($maxEle, $ele);

                if ($refEle === null) {
                    $refEle = $ele;
                } else {
                    $diff = $ele - $refEle;
                    if ($diff >= $threshold) {
                        $gain  += $diff;
                        $refEle = $ele;
                    } elseif ($diff <= -$threshold) {
                        $loss  += -$diff;
                        $refEle = $ele;
                    }
                }
            }

            $prevLat  = $lat;
            $prevLng  = $lng;
            $prevTime = $time;
        }

        if ($count === 0) {
            return [
                'point_count'      => 0,
                'started_at'       => null,
                'ended_at'         => null,
                'distance_m'       => null,
                'duration_s'       => null,
                'moving_time_s'    => null,
                'elevation_gain_m' => null,
                'elevation_loss_m' => null,
                'min_elevation'    => null,
                'max_elevation'    => null,
            ];
        }

        $hasEle = $minEle !== null;

        return [
            'point_count'      => $count,
            'started_at'       => $hasTimes ? $first : null,
            'ended_at'         => $hasTimes ? $last : null,
            'distance_m'       => number_format($distance, 3, '.', ''),
            'duration_s'       => $hasTimes
                ? (int) round(($last->getPreciseTimestamp(3) - $first->getPreciseTimestamp(3)) / 1000)
                : null,
            'moving_time_s'    => $hasTimes ? (int) round($moving) : null,
            'elevation_gain_m' => $hasEle ? number_format($gain, 3, '.', '') : null,
            'elevation_loss_m' => $hasEle ? number_format($loss, 3, '.', '') : null,
            'min_elevation'    => $hasEle ? number_format($minEle, 4, '.', '') : null,
            'max_elevation'    => $hasEle ? number_format($maxEle, 4, '.', '') : null,
        ];
    }
}
