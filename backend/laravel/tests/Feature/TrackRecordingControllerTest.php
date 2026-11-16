<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\TrackRecordingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TrackRecordingControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BATCH_A = '3f0c2a3e-8b1d-4c53-9d3e-2a6f5b7c9d10';
    private const BATCH_B = '7a1e9c40-52d6-4b8e-a1f3-0c9d84e2b6a5';

    private function payload(int $n = 2, string $batch = self::BATCH_A): array
    {
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $points[] = [
                'lat'         => -6.9 - $i * 0.0001,
                'lng'         => 107.6 + $i * 0.0001,
                'ele'         => 1200.5,
                'accuracy_m'  => 5,
                'speed_mps'   => 1.2,
                'heading_deg' => 90,
                'recorded_at' => gmdate('Y-m-d\TH:i:s', 1793494800 + $i * 10) . '.000Z',
            ];
        }

        return ['client_batch_id' => $batch, 'points' => $points];
    }

    private function startFor(User $user, string $name = 'Rekam'): int
    {
        return app(TrackRecordingService::class)->start($user, $name)->id;
    }

    public function test_guest_cannot_use_recording_endpoints(): void
    {
        $this->postJson(route('outdoor.tracks.store'))->assertUnauthorized();
        $this->getJson(route('outdoor.tracks.active'))->assertUnauthorized();
    }

    public function test_start_creates_a_recording_track(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('outdoor.tracks.store'), ['name' => 'Jalur Pagi'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Jalur Pagi')
            ->assertJsonPath('data.status', 'recording')
            ->assertJsonPath('data.point_count', 0);
    }

    public function test_start_uses_a_default_name_when_missing(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('outdoor.tracks.store'))
            ->assertCreated();

        $this->assertStringStartsWith('Rekaman ', $response->json('data.name'));
    }

    public function test_second_start_returns_409_with_the_active_track(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $this->actingAs($user)
            ->postJson(route('outdoor.tracks.store'))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'active_exists')
            ->assertJsonPath('track.id', $id);
    }

    public function test_start_rejects_a_project_of_another_user(): void
    {
        $foreign = Project::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('outdoor.tracks.store'), ['project_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }

    public function test_active_returns_the_current_recording_or_null(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('outdoor.tracks.active'))
            ->assertOk()->assertJsonPath('data', null);

        $id = $this->startFor($user);

        $this->actingAs($user)->getJson(route('outdoor.tracks.active'))
            ->assertOk()->assertJsonPath('data.id', $id);
    }

    public function test_points_are_stored_with_normalized_values(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $body = $this->payload(1);
        $body['points'][0]['recorded_at'] = '2026-11-01T08:00:00+07:00';

        $this->actingAs($user)
            ->postJson(route('outdoor.tracks.points', $id), $body)
            ->assertOk()
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.first_seq', 1)
            ->assertJsonPath('data.duplicate', false);

        $row = DB::table('track_points')->where('track_id', $id)->first();

        $this->assertEqualsWithDelta(-6.9, (float) $row->lat, 1e-9);
        $this->assertSame('01:00:00', Carbon::parse($row->recorded_at)->utc()->format('H:i:s'));
    }

    public function test_resending_a_batch_is_acknowledged_without_duplicating(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $this->actingAs($user)->postJson(route('outdoor.tracks.points', $id), $this->payload(3))->assertOk();
        $this->actingAs($user)->postJson(route('outdoor.tracks.points', $id), $this->payload(3))
            ->assertOk()
            ->assertJsonPath('data.duplicate', true)
            ->assertJsonPath('data.accepted', 3);

        $this->assertSame(3, DB::table('track_points')->where('track_id', $id)->count());
    }

    public static function invalidPayloads(): array
    {
        return [
            'missing batch id' => [fn (array $p) => array_diff_key($p, ['client_batch_id' => 1]), 'client_batch_id'],
            'non uuid batch id' => [fn (array $p) => ['client_batch_id' => 'abc'] + $p, 'client_batch_id'],
            'empty points' => [fn (array $p) => ['points' => []] + $p, 'points'],
            'too many points' => [fn (array $p) => ['points' => array_fill(0, 301, $p['points'][0])] + $p, 'points'],
            'latitude out of range' => [fn (array $p) => data_set($p, 'points.0.lat', 95), 'points.0.lat'],
            'longitude out of range' => [fn (array $p) => data_set($p, 'points.0.lng', 181), 'points.0.lng'],
            'heading out of range' => [fn (array $p) => data_set($p, 'points.0.heading_deg', 361), 'points.0.heading_deg'],
            'time without timezone' => [fn (array $p) => data_set($p, 'points.0.recorded_at', '2026-11-01 08:00:00'), 'points.0.recorded_at'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_batches_are_rejected(callable $mutate, string $errorKey): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $this->actingAs($user)
            ->postJson(route('outdoor.tracks.points', $id), $mutate($this->payload(2)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);

        $this->assertSame(0, DB::table('track_points')->where('track_id', $id)->count());
    }

    public function test_other_users_cannot_touch_a_recording(): void
    {
        $id    = $this->startFor(User::factory()->create());
        $other = User::factory()->create();

        $this->actingAs($other)->postJson(route('outdoor.tracks.points', $id), $this->payload())->assertForbidden();
        $this->actingAs($other)->postJson(route('outdoor.tracks.pause', $id))->assertForbidden();
        $this->actingAs($other)->postJson(route('outdoor.tracks.resume', $id))->assertForbidden();
        $this->actingAs($other)->postJson(route('outdoor.tracks.finish', $id))->assertForbidden();
    }

    public function test_pause_resume_finish_lifecycle(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $this->actingAs($user)->postJson(route('outdoor.tracks.pause', $id))->assertOk()->assertJsonPath('data.status', 'paused');
        $this->actingAs($user)->postJson(route('outdoor.tracks.resume', $id))->assertOk()->assertJsonPath('data.status', 'recording');
        $this->actingAs($user)->postJson(route('outdoor.tracks.finish', $id))->assertOk()->assertJsonPath('data.status', 'finished');
    }

    public function test_finished_track_cannot_be_paused(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);
        $this->actingAs($user)->postJson(route('outdoor.tracks.finish', $id))->assertOk();

        $this->actingAs($user)->postJson(route('outdoor.tracks.pause', $id))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_active');
    }

    public function test_new_batches_are_refused_after_finish(): void
    {
        $user = User::factory()->create();
        $id   = $this->startFor($user);
        $this->actingAs($user)->postJson(route('outdoor.tracks.finish', $id))->assertOk();

        $this->actingAs($user)->postJson(route('outdoor.tracks.points', $id), $this->payload(1, self::BATCH_B))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'not_active');
    }

    public function test_point_limit_is_reported_to_the_client(): void
    {
        config(['geolevel.outdoor.track_max_points' => 2]);
        $user = User::factory()->create();
        $id   = $this->startFor($user);

        $this->actingAs($user)->postJson(route('outdoor.tracks.points', $id), $this->payload(3))
            ->assertOk()
            ->assertJsonPath('data.accepted', 2)
            ->assertJsonPath('data.limit_reached', true);

        $this->actingAs($user)->postJson(route('outdoor.tracks.points', $id), $this->payload(1, self::BATCH_B))
            ->assertStatus(409)
            ->assertJsonPath('reason', 'limit_reached');
    }
}
