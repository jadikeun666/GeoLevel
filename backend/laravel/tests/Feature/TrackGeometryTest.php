<?php

namespace Tests\Feature;

use App\Models\Track;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackGeometryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config(['geolevel.outdoor.simplify_tolerance_display_m' => 2]);
    }

    private function trackWithPoints(array $points, ?User $owner = null): Track
    {
        $track = Track::factory()->create(['user_id' => ($owner ?? $this->user)->id]);

        $rows = [];
        foreach ($points as [$seq, $lat, $lng]) {
            $rows[] = [
                'track_id'    => $track->id,
                'seq'         => $seq,
                'lat'         => $lat,
                'lng'         => $lng,
                'recorded_at' => null,
            ];
        }
        DB::table('track_points')->insert($rows);

        return $track;
    }

    #[Test]
    public function guest_cannot_read_geometry(): void
    {
        $track = $this->trackWithPoints([[1, 0, 0], [2, 0, 1]]);

        $this->getJson(route('outdoor.tracks.geometry', $track))->assertUnauthorized();
    }

    #[Test]
    public function geometry_is_returned_in_lng_lat_order_with_bbox(): void
    {
        // Middle point deviates ~11.12 m > 2 m tolerance, so it is kept.
        $track = $this->trackWithPoints([[1, 0, 0], [2, 0.0001, 0.001], [3, 0, 0.002]]);

        $response = $this->actingAs($this->user)->getJson(route('outdoor.tracks.geometry', $track))->assertOk();

        $this->assertEquals([[0, 0], [0.001, 0.0001], [0.002, 0]], $response->json('data.coordinates'));
        $this->assertEquals([0, 0, 0.002, 0.0001], $response->json('data.bbox'));
        $this->assertSame(3, $response->json('data.point_count'));
        $this->assertSame(3, $response->json('data.simplified_count'));
    }

    #[Test]
    public function collinear_points_are_simplified(): void
    {
        $track = $this->trackWithPoints([
            [1, 0, 0], [2, 0, 0.001], [3, 0, 0.002], [4, 0, 0.003], [5, 0, 0.004],
        ]);

        $response = $this->actingAs($this->user)->getJson(route('outdoor.tracks.geometry', $track))->assertOk();

        $this->assertSame(5, $response->json('data.point_count'));
        $this->assertSame(2, $response->json('data.simplified_count'));
        $this->assertEquals([[0, 0], [0.004, 0]], $response->json('data.coordinates'));
    }

    #[Test]
    public function points_are_ordered_by_seq_not_insertion(): void
    {
        $track = $this->trackWithPoints([[2, 0, 0.002], [1, 0, 0.001]]);

        $response = $this->actingAs($this->user)->getJson(route('outdoor.tracks.geometry', $track))->assertOk();

        $this->assertEquals([[0.001, 0], [0.002, 0]], $response->json('data.coordinates'));
    }

    #[Test]
    public function track_without_points_returns_empty_geometry(): void
    {
        $track = Track::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->getJson(route('outdoor.tracks.geometry', $track))->assertOk();

        $this->assertSame([], $response->json('data.coordinates'));
        $this->assertNull($response->json('data.bbox'));
        $this->assertSame(0, $response->json('data.point_count'));
    }

    #[Test]
    public function other_user_gets_403(): void
    {
        $track = $this->trackWithPoints([[1, 0, 0], [2, 0, 1]]);
        $other = User::factory()->create();

        $this->actingAs($other)->getJson(route('outdoor.tracks.geometry', $track))->assertForbidden();
    }
}
