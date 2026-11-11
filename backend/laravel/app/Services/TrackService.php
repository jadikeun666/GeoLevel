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
}
