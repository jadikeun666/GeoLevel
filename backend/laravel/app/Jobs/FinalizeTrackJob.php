<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Track;
use App\Models\TrackPoint;
use App\Services\TrackStatsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Computes the final statistics of a recorded track from its stored points
 * (docs/outdoor.md, bagian 6 & 8). Safe to run more than once: it recomputes
 * from track_points, which are never modified.
 */
class FinalizeTrackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(
        public readonly int $trackId,
    ) {}

    public function handle(TrackStatsService $stats): void
    {
        $track = Track::findOrFail($this->trackId);

        // Nothing recorded: keep the track as finished with empty statistics.
        if (! TrackPoint::where('track_id', $track->id)->exists()) {
            return;
        }

        $points = DB::table('track_points')
            ->where('track_id', $track->id)
            ->orderBy('seq')
            ->select(['lat', 'lng', 'ele', 'recorded_at'])
            ->cursor();

        $result = $stats->fromPoints($points);

        $track->update([
            'started_at'       => $result['started_at'] ?? $track->started_at,
            'ended_at'         => $result['ended_at'] ?? $track->ended_at,
            'point_count'      => $result['point_count'],
            'distance_m'       => $result['distance_m'],
            'duration_s'       => $result['duration_s'],
            'moving_time_s'    => $result['moving_time_s'],
            'elevation_gain_m' => $result['elevation_gain_m'],
            'elevation_loss_m' => $result['elevation_loss_m'],
            'min_elevation'    => $result['min_elevation'],
            'max_elevation'    => $result['max_elevation'],
        ]);
    }

    /** The track stays "finished" with empty statistics; the failure is logged. */
    public function failed(Throwable $e): void
    {
        Log::error("FinalizeTrackJob failed for track {$this->trackId}: {$e->getMessage()}");

        $track = Track::find($this->trackId);

        if (! $track || $track->project_id === null) {
            return;
        }

        ActivityLog::create([
            'project_id'    => $track->project_id,
            'user_id'       => $track->user_id,
            'activity_type' => ActivityLog::TYPE_JOB_FAILED,
            'description'   => "Finalize track job failed: {$e->getMessage()}",
            'metadata'      => ['exception' => get_class($e), 'track_id' => $this->trackId],
        ]);
    }
}
