<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Track;
use App\Models\TrackPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TrackImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->other = User::factory()->create();

        config([
            'geolevel.outdoor.stats_elevation_noise_threshold_m' => 3.0,
            'geolevel.outdoor.stats_moving_speed_min_mps'        => 0.3,
        ]);
    }

    private function gpx(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">' . $body . '</gpx>';
    }

    private function upload(string $xml, string $name = 'rute.gpx'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $xml);
    }

    private function import(string $xml, array $extra = [], string $name = 'rute.gpx', ?User $as = null)
    {
        return $this->actingAs($as ?? $this->user)->postJson(
            route('outdoor.tracks.import'),
            ['file' => $this->upload($xml, $name)] + $extra
        );
    }

    #[Test]
    public function guest_cannot_import(): void
    {
        $this->postJson(route('outdoor.tracks.import'), ['file' => $this->upload($this->gpx(''))])
            ->assertUnauthorized();
    }

    #[Test]
    public function import_creates_track_points_and_statistics(): void
    {
        // Equator points 0.001 deg apart: segment length L = R * deg2rad(0.001).
        $xml = $this->gpx(
            '<trk><name>Jalur Uji</name><trkseg>'
            . '<trkpt lat="0" lon="0"><ele>100</ele><time>2026-01-01T00:00:00Z</time></trkpt>'
            . '<trkpt lat="0" lon="0.001"><ele>110</ele><time>2026-01-01T00:00:10Z</time></trkpt>'
            . '<trkpt lat="0" lon="0.002"><ele>105</ele><time>2026-01-01T00:00:20Z</time></trkpt>'
            . '</trkseg></trk>'
        );

        $this->import($xml)->assertCreated()->assertJsonPath('data.0.name', 'Jalur Uji');

        $track = Track::firstOrFail();
        $step  = (float) config('geolevel.outdoor.earth_radius_m') * deg2rad(0.001);

        $this->assertSame($this->user->id, $track->user_id);
        $this->assertSame('gpx', $track->source);
        $this->assertSame('finished', $track->status);
        $this->assertSame(3, $track->point_count);
        $this->assertEqualsWithDelta(2 * $step, (float) $track->distance_m, 0.0006);
        $this->assertSame(20, $track->duration_s);
        $this->assertSame(20, $track->moving_time_s);
        // ref=100; 110 (+10) counted, ref=110; 105 (-5) counted as loss.
        $this->assertSame('10.000', $track->elevation_gain_m);
        $this->assertSame('5.000', $track->elevation_loss_m);
        $this->assertSame('100.0000', $track->min_elevation);
        $this->assertSame('110.0000', $track->max_elevation);
        $this->assertSame('2026-01-01 00:00:00', $track->started_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('rute.gpx', $track->metadata['source_file']);

        $this->assertSame([1, 2, 3], $track->points->pluck('seq')->all());
    }

    #[Test]
    public function gpx_without_tracks_is_rejected(): void
    {
        $this->import($this->gpx('<wpt lat="1" lon="2"/>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('tracks', 0);
    }

    #[Test]
    public function multiple_tracks_import_with_fallback_names(): void
    {
        $xml = $this->gpx(
            '<trk><name>A</name><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/></trkseg></trk>'
            . '<trk><trkseg><trkpt lat="0" lon="3"/><trkpt lat="0" lon="4"/></trkseg></trk>'
        );

        $this->import($xml)->assertCreated()->assertJsonCount(2, 'data');

        $this->assertSame(['A', 'rute (2)'], Track::orderBy('id')->pluck('name')->all());
    }

    #[Test]
    public function tracks_with_fewer_than_two_points_are_skipped(): void
    {
        $xml = $this->gpx(
            '<trk><name>Satu Titik</name><trkseg><trkpt lat="0" lon="1"/></trkseg></trk>'
            . '<trk><name>Dua Titik</name><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/></trkseg></trk>'
        );

        $this->import($xml)->assertCreated()->assertJsonCount(1, 'data');

        $this->assertSame(['Dua Titik'], Track::pluck('name')->all());
    }

    #[Test]
    public function gpx_without_time_imports_with_null_times(): void
    {
        $xml = $this->gpx('<trk><trkseg><trkpt lat="0" lon="0"/><trkpt lat="0" lon="0.001"/></trkseg></trk>');

        $this->import($xml)->assertCreated();

        $track = Track::firstOrFail();
        $this->assertNull($track->duration_s);
        $this->assertNull($track->started_at);
        $this->assertNotNull($track->distance_m);
        $this->assertNull(TrackPoint::where('track_id', $track->id)->first()->recorded_at);
    }

    #[Test]
    public function invalid_gpx_is_rejected_and_nothing_is_persisted(): void
    {
        $this->import('<gpx><trk><trkseg><trkpt lat="999" lon="0"/></trkseg></trk></gpx>')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('tracks', 0);
        $this->assertDatabaseCount('track_points', 0);
    }

    #[Test]
    public function file_without_gpx_extension_is_rejected(): void
    {
        $this->import($this->gpx(''), [], 'rute.txt')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function file_larger_than_configured_limit_is_rejected(): void
    {
        config(['geolevel.outdoor.gpx_max_size_mb' => 0.0001]);

        $this->import($this->gpx(str_repeat('<wpt lat="1" lon="2"/>', 20)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function project_must_belong_to_the_importing_user(): void
    {
        $xml     = $this->gpx('<trk><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/></trkseg></trk>');
        $own     = Project::factory()->create(['user_id' => $this->user->id]);
        $foreign = Project::factory()->create(['user_id' => $this->other->id]);

        $this->import($xml, ['project_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->import($xml, ['project_id' => $own->id])
            ->assertCreated()
            ->assertJsonPath('data.0.project_id', $own->id);
    }

    #[Test]
    public function index_lists_only_own_tracks_and_filters_by_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        Track::factory()->create(['user_id' => $this->user->id, 'project_id' => $project->id]);
        Track::factory()->create(['user_id' => $this->user->id]);
        Track::factory()->create(['user_id' => $this->other->id]);

        $this->actingAs($this->user)->getJson(route('outdoor.tracks.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->user)->getJson(route('outdoor.tracks.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function owner_can_delete_track_with_its_points_and_other_user_gets_403(): void
    {
        $track = Track::factory()->create(['user_id' => $this->user->id]);
        TrackPoint::factory()->create(['track_id' => $track->id, 'seq' => 1]);

        $this->actingAs($this->other)->deleteJson(route('outdoor.tracks.destroy', $track))
            ->assertForbidden();
        $this->assertDatabaseHas('tracks', ['id' => $track->id]);

        $this->actingAs($this->user)->deleteJson(route('outdoor.tracks.destroy', $track))
            ->assertOk();
        $this->assertDatabaseMissing('tracks', ['id' => $track->id]);
        $this->assertDatabaseCount('track_points', 0);
    }
}
