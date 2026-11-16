<?php

namespace Tests\Feature;

use App\Exceptions\RecordingException;
use App\Models\Track;
use App\Models\User;
use App\Services\TrackRecordingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrackRecordingServiceTest extends TestCase
{
    use RefreshDatabase;

    private const BATCH_A = '3f0c2a3e-8b1d-4c53-9d3e-2a6f5b7c9d10';
    private const BATCH_B = '7a1e9c40-52d6-4b8e-a1f3-0c9d84e2b6a5';
    private const BATCH_C = 'c2d8f6b1-04a7-4e39-8b5c-91e7a3d0f642';

    private function service(): TrackRecordingService
    {
        return app(TrackRecordingService::class);
    }

    /** Synthetic points; values are placeholders, tests only assert seq/counts/state. */
    private function points(int $n, int $offset = 0): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $k = $offset + $i;
            $rows[] = [
                'lat'         => number_format(-6.9 - $k * 0.0001, 8, '.', ''),
                'lng'         => number_format(107.6 + $k * 0.0001, 8, '.', ''),
                'ele'         => '1200.0000',
                'accuracy_m'  => '5.000',
                'recorded_at' => gmdate('Y-m-d H:i:s', 1793494800 + $k * 10) . '.000+00:00',
            ];
        }

        return $rows;
    }

    private function assertReason(string $reason, callable $fn): void
    {
        try {
            $fn();
        } catch (RecordingException $e) {
            $this->assertSame($reason, $e->reason);

            return;
        }

        $this->fail("Expected RecordingException ({$reason}).");
    }

    private function seqs(Track $track): array
    {
        return DB::table('track_points')->where('track_id', $track->id)->orderBy('seq')->pluck('seq')->map(fn ($s) => (int) $s)->all();
    }

    public function test_start_creates_a_recording_track(): void
    {
        $user  = User::factory()->create();
        $track = $this->service()->start($user, 'Jalur Pagi');

        $this->assertSame($user->id, $track->user_id);
        $this->assertSame('recorded', $track->source);
        $this->assertSame('recording', $track->status);
        $this->assertSame(0, $track->point_count);
        $this->assertNotNull($track->started_at);
    }

    public function test_second_start_is_refused_while_a_recording_is_active(): void
    {
        $user  = User::factory()->create();
        $first = $this->service()->start($user, 'Satu');

        try {
            $this->service()->start($user, 'Dua');
            $this->fail('Expected RecordingException.');
        } catch (RecordingException $e) {
            $this->assertSame(RecordingException::ACTIVE_EXISTS, $e->reason);
            $this->assertSame($first->id, $e->track->id);
        }

        $this->assertSame(1, Track::where('user_id', $user->id)->count());
    }

    public function test_start_is_allowed_after_finish_and_for_other_users(): void
    {
        $user  = User::factory()->create();
        $first = $this->service()->start($user, 'Satu');
        $this->service()->finish($first);

        $this->assertSame('recording', $this->service()->start($user, 'Dua')->status);
        $this->assertSame('recording', $this->service()->start(User::factory()->create(), 'Lain')->status);
    }

    public function test_batches_get_consecutive_server_assigned_seq(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');

        $a = $this->service()->appendBatch($track, self::BATCH_A, $this->points(3));
        $b = $this->service()->appendBatch($track, self::BATCH_B, $this->points(2, 3));

        $this->assertSame(['duplicate' => false, 'first_seq' => 1, 'accepted' => 3, 'point_count' => 3, 'limit_reached' => false], $a);
        $this->assertSame(['duplicate' => false, 'first_seq' => 4, 'accepted' => 2, 'point_count' => 5, 'limit_reached' => false], $b);
        $this->assertSame([1, 2, 3, 4, 5], $this->seqs($track));
        $this->assertSame(5, $track->fresh()->point_count);
    }

    public function test_same_client_batch_id_is_not_inserted_twice(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');

        $this->service()->appendBatch($track, self::BATCH_A, $this->points(3));
        $retry = $this->service()->appendBatch($track, self::BATCH_A, $this->points(3));

        $this->assertTrue($retry['duplicate']);
        $this->assertSame(1, $retry['first_seq']);
        $this->assertSame(3, $retry['accepted']);
        $this->assertSame([1, 2, 3], $this->seqs($track));
        $this->assertSame(3, $track->fresh()->point_count);
    }

    public function test_points_keep_their_fields(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');
        $this->service()->appendBatch($track, self::BATCH_A, $this->points(1));

        $row = DB::table('track_points')->where('track_id', $track->id)->first();

        $this->assertSame(1, (int) $row->seq);
        $this->assertEquals('-6.9', rtrim(rtrim((string) $row->lat, '0'), '.'));
        $this->assertNotNull($row->recorded_at);
    }

    public function test_paused_track_still_accepts_in_flight_batches(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');
        $this->service()->pause($track);

        $result = $this->service()->appendBatch($track, self::BATCH_A, $this->points(2));

        $this->assertSame(2, $result['accepted']);
    }

    public function test_finished_track_refuses_new_batches_but_answers_old_retries(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');
        $this->service()->appendBatch($track, self::BATCH_A, $this->points(2));
        $this->service()->finish($track);

        $this->assertReason(RecordingException::NOT_ACTIVE, fn () => $this->service()->appendBatch($track, self::BATCH_B, $this->points(1, 2)));

        $retry = $this->service()->appendBatch($track, self::BATCH_A, $this->points(2));
        $this->assertTrue($retry['duplicate']);
    }

    public function test_point_limit_truncates_the_last_batch_then_refuses(): void
    {
        config(['geolevel.outdoor.track_max_points' => 5]);
        $track = $this->service()->start(User::factory()->create(), 'Rekam');

        $a = $this->service()->appendBatch($track, self::BATCH_A, $this->points(3));
        $b = $this->service()->appendBatch($track, self::BATCH_B, $this->points(4, 3));

        $this->assertFalse($a['limit_reached']);
        $this->assertSame(2, $b['accepted']);
        $this->assertSame(4, $b['first_seq']);
        $this->assertTrue($b['limit_reached']);
        $this->assertSame([1, 2, 3, 4, 5], $this->seqs($track));

        $this->assertReason(RecordingException::LIMIT_REACHED, fn () => $this->service()->appendBatch($track, self::BATCH_C, $this->points(1, 7)));

        $retry = $this->service()->appendBatch($track, self::BATCH_B, $this->points(4, 3));
        $this->assertTrue($retry['duplicate']);
        $this->assertSame(2, $retry['accepted']);
    }

    public function test_pause_resume_finish_transitions_are_idempotent(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');

        $this->assertSame('paused', $this->service()->pause($track)->status);
        $this->assertSame('paused', $this->service()->pause($track)->status);
        $this->assertSame('recording', $this->service()->resume($track)->status);
        $this->assertSame('recording', $this->service()->resume($track)->status);

        $finished = $this->service()->finish($track);
        $this->assertSame('finished', $finished->status);
        $this->assertNotNull($finished->ended_at);
        $this->assertSame('finished', $this->service()->finish($track)->status);
    }

    public function test_finished_track_cannot_be_paused_or_resumed(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');
        $this->service()->finish($track);

        $this->assertReason(RecordingException::NOT_ACTIVE, fn () => $this->service()->pause($track));
        $this->assertReason(RecordingException::NOT_ACTIVE, fn () => $this->service()->resume($track));
    }

    public function test_empty_batch_is_rejected(): void
    {
        $track = $this->service()->start(User::factory()->create(), 'Rekam');

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->appendBatch($track, self::BATCH_A, []);
    }
}
