<?php

namespace App\Services;

use App\Exceptions\GpxParseException;
use App\Models\Track;
use App\Models\TrackPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Imports the tracks of a GPX file as finished tracks (source = gpx).
 * Raw points are bulk-inserted (insert-only, seq assigned here, 1-based);
 * statistics come from TrackStatsService in one pass over the parsed points.
 */
class TrackImportService
{
    private const INSERT_CHUNK = 1000;

    public function __construct(
        private readonly GpxService $gpx,
        private readonly TrackStatsService $stats,
    ) {
    }

    /**
     * @return list<Track>
     *
     * @throws GpxParseException
     */
    public function importFile(
        User $user,
        string $path,
        string $fallbackName,
        ?int $projectId = null,
        ?string $originalFilename = null,
    ): array {
        $parsed = $this->gpx->parseFile($path);

        // A track needs at least 2 points to form a line.
        $tracks = array_values(array_filter(
            $parsed['tracks'],
            fn (array $track) => count($track['points']) >= 2
        ));

        if ($tracks === []) {
            throw new GpxParseException('File GPX tidak memuat track dengan minimal 2 titik.');
        }

        unset($parsed);

        return DB::transaction(function () use ($user, $tracks, $fallbackName, $projectId, $originalFilename) {
            $created = [];

            foreach ($tracks as $index => $parsedTrack) {
                $position = $index + 1;
                $name     = $parsedTrack['name']
                    ?? (count($tracks) > 1 ? "{$fallbackName} ({$position})" : $fallbackName);

                $stats = $this->stats->fromPoints($parsedTrack['points']);

                $track = Track::create([
                    'user_id'          => $user->id,
                    'project_id'       => $projectId,
                    'name'             => mb_substr($name, 0, 150),
                    'source'           => 'gpx',
                    'status'           => 'finished',
                    'started_at'       => $stats['started_at'],
                    'ended_at'         => $stats['ended_at'],
                    'point_count'      => $stats['point_count'],
                    'distance_m'       => $stats['distance_m'],
                    'duration_s'       => $stats['duration_s'],
                    'moving_time_s'    => $stats['moving_time_s'],
                    'elevation_gain_m' => $stats['elevation_gain_m'],
                    'elevation_loss_m' => $stats['elevation_loss_m'],
                    'min_elevation'    => $stats['min_elevation'],
                    'max_elevation'    => $stats['max_elevation'],
                    'metadata'         => ['source_file' => $originalFilename === null ? null : mb_substr($originalFilename, 0, 255)],
                ]);

                $this->insertPoints($track, $parsedTrack['points']);

                $created[] = $track;
            }

            return $created;
        });
    }

    private function insertPoints(Track $track, array $points): void
    {
        $seq = 1;

        foreach (array_chunk($points, self::INSERT_CHUNK) as $chunk) {
            $rows = [];
            foreach ($chunk as $point) {
                $rows[] = [
                    'track_id'    => $track->id,
                    'seq'         => $seq++,
                    'lat'         => $point['lat'],
                    'lng'         => $point['lng'],
                    'ele'         => $point['ele'],
                    'accuracy_m'  => null,
                    'speed_mps'   => null,
                    'heading_deg' => null,
                    'recorded_at' => $point['recorded_at'],
                ];
            }

            TrackPoint::insert($rows);
        }
    }
}
