<?php

namespace App\Services;

use App\Exceptions\RecordingException;
use App\Models\Track;
use App\Models\TrackPoint;
use App\Models\TrackPointBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Live track recording (docs/outdoor.md, bagian 6 & 8).
 *
 * - Batches are idempotent per (track, client_batch_id); a retry is answered, not re-inserted.
 * - `seq` is assigned here, under a row lock on the track, never by the client.
 * - track_points stay insert-only. Final statistics are NOT computed here:
 *   finish() only flips the status; FinalizeTrackJob computes stats afterwards.
 */
class TrackRecordingService
{
    private const INSERT_CHUNK = 1000;

    /** Statuses in which a track still accepts points. */
    private const ACTIVE = ['recording', 'paused'];

    public function activeFor(User $user): ?Track
    {
        return Track::where('user_id', $user->id)
            ->whereIn('status', self::ACTIVE)
            ->latest('id')
            ->first();
    }

    /**
     * @throws RecordingException when the user already has an active recording
     */
    public function start(User $user, string $name, ?int $projectId = null): Track
    {
        return DB::transaction(function () use ($user, $name, $projectId) {
            // Serialize concurrent starts for the same user.
            User::whereKey($user->id)->lockForUpdate()->first();

            $active = $this->activeFor($user);
            if ($active !== null) {
                throw RecordingException::activeExists($active);
            }

            return Track::create([
                'user_id'     => $user->id,
                'project_id'  => $projectId,
                'name'        => mb_substr($name, 0, 150),
                'source'      => 'recorded',
                'status'      => 'recording',
                'started_at'  => now(),
                'point_count' => 0,
            ]);
        });
    }

    /**
     * @param  list<array{lat:string,lng:string,ele?:?string,accuracy_m?:?string,speed_mps?:?string,heading_deg?:?string,recorded_at:string}>  $points
     * @return array{duplicate:bool,first_seq:int,accepted:int,point_count:int,limit_reached:bool}
     *
     * @throws RecordingException
     */
    public function appendBatch(Track $track, string $clientBatchId, array $points): array
    {
        if ($points === []) {
            throw new \InvalidArgumentException('A batch needs at least one point.');
        }

        $points = array_values($points);
        $max    = (int) config('geolevel.outdoor.track_max_points');

        return DB::transaction(function () use ($track, $clientBatchId, $points, $max) {
            $locked = Track::whereKey($track->id)->lockForUpdate()->firstOrFail();

            $existing = TrackPointBatch::where('track_id', $locked->id)
                ->where('client_batch_id', $clientBatchId)
                ->first();

            if ($existing !== null) {
                return [
                    'duplicate'     => true,
                    'first_seq'     => $existing->first_seq,
                    'accepted'      => $existing->point_count,
                    'point_count'   => $locked->point_count,
                    'limit_reached' => $locked->point_count >= $max,
                ];
            }

            if (! in_array($locked->status, self::ACTIVE, true)) {
                throw RecordingException::notActive($locked);
            }

            $lastSeq   = (int) TrackPoint::where('track_id', $locked->id)->max('seq');
            $remaining = $max - $lastSeq;

            if ($remaining <= 0) {
                throw RecordingException::limitReached($locked);
            }

            $accepted = array_slice($points, 0, $remaining);
            $count    = count($accepted);
            $total    = $lastSeq + $count;

            $this->insertPoints($locked->id, $lastSeq, $accepted);

            $locked->update(['point_count' => $total]);

            TrackPointBatch::create([
                'track_id'        => $locked->id,
                'client_batch_id' => $clientBatchId,
                'first_seq'       => $lastSeq + 1,
                'point_count'     => $count,
                'received_at'     => now(),
            ]);

            return [
                'duplicate'     => false,
                'first_seq'     => $lastSeq + 1,
                'accepted'      => $count,
                'point_count'   => $total,
                'limit_reached' => $total >= $max,
            ];
        });
    }

    /** @throws RecordingException */
    public function pause(Track $track): Track
    {
        return $this->transition($track, ['recording'], 'paused');
    }

    /** @throws RecordingException */
    public function resume(Track $track): Track
    {
        return $this->transition($track, ['paused'], 'recording');
    }

    /**
     * Marks the track finished. `ended_at` is provisional (now); FinalizeTrackJob
     * overwrites it, with the rest of the statistics, from the stored points.
     *
     * @throws RecordingException
     */
    public function finish(Track $track): Track
    {
        return $this->transition($track, self::ACTIVE, 'finished', ['ended_at' => now()]);
    }

    /**
     * Idempotent: asking for the status the track already has returns it unchanged.
     *
     * @param  list<string>  $from
     */
    private function transition(Track $track, array $from, string $to, array $attributes = []): Track
    {
        return DB::transaction(function () use ($track, $from, $to, $attributes) {
            $locked = Track::whereKey($track->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === $to) {
                return $locked;
            }

            if (! in_array($locked->status, $from, true)) {
                throw RecordingException::notActive($locked);
            }

            $locked->update(['status' => $to] + $attributes);

            return $locked;
        });
    }

    private function insertPoints(int $trackId, int $lastSeq, array $points): void
    {
        $seq = $lastSeq;

        foreach (array_chunk($points, self::INSERT_CHUNK) as $chunk) {
            $rows = [];
            foreach ($chunk as $point) {
                $rows[] = [
                    'track_id'    => $trackId,
                    'seq'         => ++$seq,
                    'lat'         => $point['lat'],
                    'lng'         => $point['lng'],
                    'ele'         => $point['ele'] ?? null,
                    'accuracy_m'  => $point['accuracy_m'] ?? null,
                    'speed_mps'   => $point['speed_mps'] ?? null,
                    'heading_deg' => $point['heading_deg'] ?? null,
                    'recorded_at' => $point['recorded_at'],
                ];
            }

            TrackPoint::insert($rows);
        }
    }
}
