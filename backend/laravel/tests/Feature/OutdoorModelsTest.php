<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Track;
use App\Models\TrackPoint;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutdoorModelsTest extends TestCase
{
    use RefreshDatabase;

    private function assertFillableMatchesColumns(string $modelClass, array $ignore): void
    {
        $model    = new $modelClass();
        $expected = array_values(array_diff(Schema::getColumnListing($model->getTable()), $ignore));

        $this->assertEqualsCanonicalizing($expected, $model->getFillable());
    }

    #[Test]
    public function fillable_covers_every_column_of_each_outdoor_model(): void
    {
        $this->assertFillableMatchesColumns(Waypoint::class, ['id', 'created_at', 'updated_at']);
        $this->assertFillableMatchesColumns(Track::class, ['id', 'created_at', 'updated_at']);
        $this->assertFillableMatchesColumns(TrackPoint::class, ['id']);
    }

    #[Test]
    public function waypoint_coordinates_are_cast_to_fixed_decimal_strings(): void
    {
        $waypoint = Waypoint::factory()->create([
            'lat'           => '-6.12345678',
            'lng'           => '106.87654321',
            'elevation_ref' => '12.3456',
        ])->fresh();

        $this->assertSame('-6.12345678', $waypoint->lat);
        $this->assertSame('106.87654321', $waypoint->lng);
        $this->assertSame('12.3456', $waypoint->elevation_ref);
    }

    #[Test]
    public function waypoint_can_exist_without_project(): void
    {
        $waypoint = Waypoint::factory()->create()->fresh();

        $this->assertNull($waypoint->project_id);
        $this->assertNull($waypoint->project);
        $this->assertInstanceOf(User::class, $waypoint->user);
    }

    #[Test]
    public function deleting_project_nulls_project_id_on_waypoint_and_track(): void
    {
        $user     = User::factory()->create();
        $project  = Project::factory()->create(['user_id' => $user->id]);
        $waypoint = Waypoint::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);
        $track    = Track::factory()->create(['user_id' => $user->id, 'project_id' => $project->id]);

        $project->delete();

        $this->assertNull($waypoint->fresh()->project_id);
        $this->assertNull($track->fresh()->project_id);
    }

    #[Test]
    public function deleting_user_cascades_to_waypoints_tracks_and_points(): void
    {
        $user  = User::factory()->create();
        $track = Track::factory()->create(['user_id' => $user->id]);
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 1]);
        Waypoint::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $this->assertDatabaseCount('waypoints', 0);
        $this->assertDatabaseCount('tracks', 0);
        $this->assertDatabaseCount('track_points', 0);
    }

    #[Test]
    public function track_metadata_defaults_to_empty_array_and_casts_json(): void
    {
        $default = Track::factory()->create()->fresh();
        $this->assertSame([], $default->metadata);

        $custom = Track::factory()->create(['metadata' => ['creator' => 'test']])->fresh();
        $this->assertSame(['creator' => 'test'], $custom->metadata);
    }

    #[Test]
    public function track_stats_are_cast_with_expected_precision(): void
    {
        $track = Track::factory()->create([
            'distance_m'       => '1234.5',
            'elevation_gain_m' => '10.25',
            'min_elevation'    => '5.5',
            'point_count'      => 3,
        ])->fresh();

        $this->assertSame('1234.500', $track->distance_m);
        $this->assertSame('10.250', $track->elevation_gain_m);
        $this->assertSame('5.5000', $track->min_elevation);
        $this->assertSame(3, $track->point_count);
    }

    #[Test]
    public function track_points_relation_is_ordered_by_seq(): void
    {
        $track = Track::factory()->create();
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 2]);
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 1]);
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 3]);

        $this->assertSame([1, 2, 3], $track->points->pluck('seq')->all());
    }

    #[Test]
    public function track_point_seq_is_unique_per_track(): void
    {
        $track = Track::factory()->create();
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 1]);

        $this->expectException(QueryException::class);

        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 1]);
    }

    #[Test]
    public function track_point_is_immutable_once_inserted(): void
    {
        $point = TrackPoint::factory()->create(['seq' => 1]);

        $this->expectException(LogicException::class);

        $point->update(['lat' => '-7.00000000']);
    }

    #[Test]
    public function track_point_recorded_at_is_carbon(): void
    {
        $point = TrackPoint::factory()->create(['recorded_at' => '2026-01-02 03:04:05'])->fresh();

        $this->assertInstanceOf(Carbon::class, $point->recorded_at);
    }
}
