<?php

namespace Tests\Feature;

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrackProfileTest extends TestCase
{
    use RefreshDatabase;

    /** @param list<array{0: float, 1: ?float}> $points [lng, ele] along the equator */
    private function trackWith(User $user, array $points): Track
    {
        $track = Track::factory()->create(['user_id' => $user->id]);
        DB::table('track_points')->insert(array_map(fn (array $p, int $i) => [
            'track_id' => $track->id, 'seq' => $i + 1, 'lat' => '0.00000000',
            'lng' => number_format($p[0], 8, '.', ''), 'ele' => $p[1],
        ], $points, array_keys($points)));

        return $track;
    }

    public function test_profile_has_cumulative_distance_and_elevation(): void
    {
        $user  = User::factory()->create();
        $track = $this->trackWith($user, [[0.0, 10.0], [0.001, 12.0], [0.002, 11.0]]);

        $r = $this->actingAs($user)->getJson(route('outdoor.tracks.profile', $track))->assertOk();

        // 0.001 deg of longitude at the equator = 111.195080 m
        // JSON drops the trailing .0, so compare numerically (assertEquals), not by type.
        $this->assertEquals([
            ['d' => 0.0, 'ele' => 10.0], ['d' => 111.2, 'ele' => 12.0], ['d' => 222.4, 'ele' => 11.0],
        ], $r->json('data.points'));
        $this->assertTrue($r->json('data.has_elevation'));
        $this->assertSame(3, $r->json('data.point_count'));
    }

    public function test_track_without_elevation_has_no_profile_points(): void
    {
        $user  = User::factory()->create();
        $track = $this->trackWith($user, [[0.0, null], [0.001, null]]);

        $r = $this->actingAs($user)->getJson(route('outdoor.tracks.profile', $track))->assertOk();

        $this->assertFalse($r->json('data.has_elevation'));
        $this->assertSame([], $r->json('data.points'));
    }

    public function test_large_track_is_reduced_but_keeps_the_peak_and_the_ends(): void
    {
        config(['geolevel.outdoor.profile_max_points' => 10]);
        $user   = User::factory()->create();
        $points = [];
        for ($i = 0; $i < 100; $i++) {
            $points[] = [$i * 0.0001, $i === 50 ? 500.0 : (float) ($i % 3)];
        }
        $track = $this->trackWith($user, $points);

        $r   = $this->actingAs($user)->getJson(route('outdoor.tracks.profile', $track))->assertOk();
        $out = $r->json('data.points');

        $this->assertLessThanOrEqual(12, count($out));      // 5 buckets x (min, max) + first + last
        $this->assertEquals(0.0, $out[0]['d']);
        $this->assertContainsEquals(500.0, array_column($out, 'ele'));
        $this->assertSame(100, $r->json('data.point_count'));
    }

    public function test_other_users_track_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $track = $this->trackWith($owner, [[0.0, 1.0], [0.001, 2.0]]);

        $this->actingAs(User::factory()->create())->getJson(route('outdoor.tracks.profile', $track))->assertForbidden();
    }
}
