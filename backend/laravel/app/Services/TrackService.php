<?php

namespace App\Services;

use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TrackService
{
    public function __construct(private readonly GeoMathService $geo)
    {
    }

    /** Track summaries only (never the raw points). */
    public function listFor(User $user, ?int $projectId = null): Collection
    {
        return Track::query()
            ->where('user_id', $user->id)
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('id')
            ->get();
    }

    public function delete(Track $track): void
    {
        $track->delete();
    }

    /**
     * Simplified geometry for map display (GeoJSON order [lng, lat]).
     * Derived data only; raw track_points are never modified.
     *
     * @return array{coordinates: list<array{0: float, 1: float}>, bbox: ?array, point_count: int, simplified_count: int, tolerance_m: float}
     */
    public function geometry(Track $track): array
    {
        $tolerance = (float) config('geolevel.outdoor.simplify_tolerance_display_m');

        $coords = [];
        $minLat = $maxLat = $minLng = $maxLng = null;

        $rows = DB::table('track_points')
            ->where('track_id', $track->id)
            ->orderBy('seq')
            ->select('lat', 'lng')
            ->cursor();

        foreach ($rows as $row) {
            $lat = (float) $row->lat;
            $lng = (float) $row->lng;

            $coords[] = [$lat, $lng];

            $minLat = $minLat === null ? $lat : min($minLat, $lat);
            $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
            $minLng = $minLng === null ? $lng : min($minLng, $lng);
            $maxLng = $maxLng === null ? $lng : max($maxLng, $lng);
        }

        $count = count($coords);
        if ($count === 0) {
            return [
                'coordinates'      => [],
                'bbox'             => null,
                'point_count'      => 0,
                'simplified_count' => 0,
                'tolerance_m'      => $tolerance,
            ];
        }

        $kept        = $this->geo->simplify($coords, $tolerance);
        $coordinates = array_map(fn (int $i) => [$coords[$i][1], $coords[$i][0]], $kept);

        return [
            'coordinates'      => $coordinates,
            'bbox'             => [$minLng, $minLat, $maxLng, $maxLat],
            'point_count'      => $count,
            'simplified_count' => count($coordinates),
            'tolerance_m'      => $tolerance,
        ];
    }

    /**
     * Elevation profile for display: cumulative distance (m, over ALL points) vs GPS elevation.
     * shortcut: min/max per bucket keeps peaks and valleys but not the exact shape in between; use RDP in the distance-elevation plane if fidelity matters.
     *
     * @return array{points: list<array{d: float, ele: float}>, has_elevation: bool, point_count: int, gain_m: ?string, loss_m: ?string, min_ele: ?string, max_ele: ?string}
     */
    public function profile(Track $track): array
    {
        $max  = max(4, (int) config('geolevel.outdoor.profile_max_points'));
        $pts  = [];
        $d    = 0.0;
        $prev = null;
        $all  = 0;

        $rows = DB::table('track_points')
            ->where('track_id', $track->id)
            ->orderBy('seq')
            ->select('lat', 'lng', 'ele')
            ->cursor();

        foreach ($rows as $row) {
            $all++;
            if ($prev !== null) {
                $d += $this->geo->haversine($prev->lat, $prev->lng, $row->lat, $row->lng);
            }
            $prev = $row;
            if ($row->ele !== null) {
                $pts[] = [$d, (float) $row->ele];
            }
        }

        $n = count($pts);
        if ($n > $max) {
            $size = (int) ceil($n / intdiv($max, 2));
            $keep = [0 => true, $n - 1 => true];
            for ($start = 0; $start < $n; $start += $size) {
                $end = min($start + $size, $n);
                $lo  = $hi = $start;
                for ($i = $start + 1; $i < $end; $i++) {
                    if ($pts[$i][1] < $pts[$lo][1]) {
                        $lo = $i;
                    }
                    if ($pts[$i][1] > $pts[$hi][1]) {
                        $hi = $i;
                    }
                }
                $keep[$lo] = true;
                $keep[$hi] = true;
            }
            ksort($keep);
            $source = $pts;
            $pts    = array_map(fn (int $i) => $source[$i], array_keys($keep));
        }

        return [
            'points'        => array_map(fn (array $p) => ['d' => round($p[0], 1), 'ele' => round($p[1], 2)], $pts),
            'has_elevation' => $n > 0,
            'point_count'   => $all,
            'gain_m'        => $track->elevation_gain_m,
            'loss_m'        => $track->elevation_loss_m,
            'min_ele'       => $track->min_elevation,
            'max_ele'       => $track->max_elevation,
        ];
    }
}
