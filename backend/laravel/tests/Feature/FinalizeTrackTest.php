<?php

namespace Tests\Feature;

use App\Events\TrackFinished;
use App\Jobs\FinalizeTrackJob;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Track;
use App\Models\User;
use App\Services\TrackRecordingService;
use App\Services\TrackStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FinalizeTrackTest extends TestCase
{
    use RefreshDatabase;

    private const BATCH_ID = '3f0c2a3e-8b1d-4c53-9d3e-2a6f5b7c9d10';
    private const BASE     = 1793494800; // synthetic base timestamp, only offsets matter

    private function service(): TrackRecordingService
    {
        return app(TrackRecordingService::class);
    }

    /** n points, 10 s apart, constant elevation, ~15.7 m between points. */
    private function points(int $n): array
    {
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'lat'         => number_format(-6.9 - $i * 0.0001, 8, '.', ''),
                'lng'         => number_format(107.6 + $i * 0.0001, 8, '.', ''),
                'ele'         => '1200.0000',
                'recorded_at' => gmdate('Y-m-d H:i:s', self::BASE + $i * 10) . '.000+00:00',
            ];
        }

        return $rows;
    }

    private function recordedTrack(User $user, int $points, ?int $projectId = null): Track
    {
        $track = $this->service()->start($user, 'Rekam', $projectId);
        if ($points > 0) {
            $this->service()->appendBatch($track, self::BATCH_ID, $this->points($points));
        }

        return $track;
    }

    public function test_finishing_a_recorded_track_computes_its_statistics(): void
    {
        $track = $this->recordedTrack(User::factory()->create(), 4);

        $this->service()->finish($track);
        $track->refresh();

        $this->assertSame('finished', $track->status);
        $this->assertSame(4, $track->point_count);
        $this->assertSame(30, $track->duration_s);
        $this->assertSame(30, $track->moving_time_s);
        $this->assertGreaterThan(0, (float) $track->distance_m);
        $this->assertSame(gmdate('Y-m-d H:i:s', self::BASE), $track->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(gmdate('Y-m-d H:i:s', self::BASE + 30), $track->ended_at->utc()->format('Y-m-d H:i:s'));
        $this->assertEquals(0.0, (float) $track->elevation_gain_m);
        $this->assertEquals(0.0, (float) $track->elevation_loss_m);
        $this->assertEquals(1200.0, (float) $track->min_elevation);
        $this->assertEquals(1200.0, (float) $track->max_elevation);
    }

    public function test_finishing_without_points_leaves_statistics_empty(): void
    {
        $track = $this->recordedTrack(User::factory()->create(), 0);

        $this->service()->finish($track);
        $track->refresh();

        $this->assertSame('finished', $track->status);
        $this->assertSame(0, $track->point_count);
        $this->assertNull($track->distance_m);
        $this->assertNull($track->duration_s);
    }

    public function test_running_the_job_twice_gives_the_same_result(): void
    {
        $track = $this->recordedTrack(User::factory()->create(), 4);
        $this->service()->finish($track);
        $first = $track->fresh()->only(['distance_m', 'duration_s', 'moving_time_s', 'point_count']);

        (new FinalizeTrackJob($track->id))->handle(app(TrackStatsService::class));

        $this->assertSame($first, $track->fresh()->only(['distance_m', 'duration_s', 'moving_time_s', 'point_count']));
    }

    public function test_finish_announces_the_end_only_once(): void
    {
        Event::fake([TrackFinished::class]);
        $user  = User::factory()->create();
        $track = $this->recordedTrack($user, 2);

        $this->service()->finish($track);
        $this->service()->finish($track);

        Event::assertDispatchedTimes(TrackFinished::class, 1);
        Event::assertDispatched(TrackFinished::class, fn (TrackFinished $e) => $e->trackId === $track->id
            && $e->userId === $user->id
            && $e->projectId === null);
    }

    public function test_finish_queues_the_finalize_job(): void
    {
        Queue::fake();
        $track = $this->recordedTrack(User::factory()->create(), 2);

        $this->service()->finish($track);

        Queue::assertPushed(FinalizeTrackJob::class, fn (FinalizeTrackJob $job) => $job->trackId === $track->id);
    }

    public function test_finish_logs_activity_for_a_track_with_a_project(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $track   = $this->recordedTrack($user, 2, $project->id);

        $this->service()->finish($track);
        $this->service()->finish($track);

        $logs = ActivityLog::where('activity_type', ActivityLog::TYPE_TRACK_FINISHED)->get();
        $this->assertCount(1, $logs);
        $this->assertSame($project->id, $logs->first()->project_id);
        $this->assertSame($track->id, $logs->first()->metadata['track_id']);
    }

    public function test_finish_writes_no_activity_log_for_a_track_without_a_project(): void
    {
        $track = $this->recordedTrack(User::factory()->create(), 2);

        $this->service()->finish($track);

        $this->assertSame(0, ActivityLog::count());
    }

    public function test_failed_job_logs_and_keeps_the_track_finished(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $track   = $this->recordedTrack($user, 2, $project->id);
        $this->service()->finish($track);

        (new FinalizeTrackJob($track->id))->failed(new \RuntimeException('boom'));

        $this->assertSame('finished', $track->fresh()->status);
        $this->assertSame(1, ActivityLog::where('activity_type', ActivityLog::TYPE_JOB_FAILED)->count());
    }

    public function test_failed_job_without_a_project_writes_no_activity_log(): void
    {
        $track = $this->recordedTrack(User::factory()->create(), 2);
        $this->service()->finish($track);

        (new FinalizeTrackJob($track->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(0, ActivityLog::count());
    }
}
