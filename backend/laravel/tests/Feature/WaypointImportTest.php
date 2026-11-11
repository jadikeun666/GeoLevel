<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WaypointImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user  = User::factory()->create();
        $this->other = User::factory()->create();
    }

    private function gpx(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<gpx version="1.1" creator="test" xmlns="http://www.topografix.com/GPX/1/1">' . $body . '</gpx>';
    }

    private function import(string $xml, array $extra = [], string $name = 'titik.gpx')
    {
        return $this->actingAs($this->user)->postJson(
            route('outdoor.waypoints.import'),
            ['file' => UploadedFile::fake()->createWithContent($name, $xml)] + $extra
        );
    }

    #[Test]
    public function guest_cannot_import(): void
    {
        $this->postJson(route('outdoor.waypoints.import'), [
            'file' => UploadedFile::fake()->createWithContent('titik.gpx', $this->gpx('')),
        ])->assertUnauthorized();
    }

    #[Test]
    public function import_creates_waypoints_owned_by_user_with_gpx_source(): void
    {
        $xml = $this->gpx(
            '<wpt lat="-6.2" lon="106.8"><ele>12.5</ele><name>Pos A</name><desc>Dekat sungai</desc></wpt>'
            . '<wpt lat="-6.3" lon="106.9"/>'
        );

        $this->import($xml)->assertCreated()->assertJsonPath('imported', 2);

        $this->assertDatabaseCount('waypoints', 2);
        $first = Waypoint::where('name', 'Pos A')->firstOrFail();
        $this->assertSame($this->user->id, $first->user_id);
        $this->assertSame('gpx', $first->source);
        $this->assertSame('generic', $first->category);
        $this->assertSame('Dekat sungai', $first->notes);
        $this->assertSame('12.5000', $first->elevation_ref);
        $this->assertNotNull($first->created_at);
        $this->assertDatabaseHas('waypoints', ['name' => 'Waypoint 2', 'user_id' => $this->user->id]);
    }

    #[Test]
    public function tracks_in_the_file_are_ignored(): void
    {
        $xml = $this->gpx(
            '<wpt lat="1" lon="2"><name>W</name></wpt>'
            . '<trk><trkseg><trkpt lat="0" lon="1"/><trkpt lat="0" lon="2"/></trkseg></trk>'
        );

        $this->import($xml)->assertCreated()->assertJsonPath('imported', 1);

        $this->assertDatabaseCount('tracks', 0);
    }

    #[Test]
    public function gpx_without_waypoints_is_rejected(): void
    {
        $this->import($this->gpx('<trk><trkseg><trkpt lat="0" lon="1"/></trkseg></trk>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function invalid_gpx_is_rejected(): void
    {
        $this->import($this->gpx('<wpt lat="999" lon="2"/>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('waypoints', 0);
    }

    #[Test]
    public function file_waypoint_count_limit_is_enforced_and_nothing_is_persisted(): void
    {
        config(['geolevel.outdoor.gpx_max_waypoints' => 1]);

        $this->import($this->gpx('<wpt lat="1" lon="2"/><wpt lat="3" lon="4"/>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('waypoints', 0);
    }

    #[Test]
    public function per_user_limit_is_enforced_all_or_nothing(): void
    {
        config(['geolevel.outdoor.waypoint_max_per_user' => 2]);
        Waypoint::factory()->create(['user_id' => $this->user->id]);

        $this->import($this->gpx('<wpt lat="1" lon="2"/><wpt lat="3" lon="4"/>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('waypoints', 1);
    }

    #[Test]
    public function project_must_belong_to_the_importing_user(): void
    {
        $xml     = $this->gpx('<wpt lat="1" lon="2"/>');
        $own     = Project::factory()->create(['user_id' => $this->user->id]);
        $foreign = Project::factory()->create(['user_id' => $this->other->id]);

        $this->import($xml, ['project_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);

        $this->import($xml, ['project_id' => $own->id])->assertCreated();

        $this->assertDatabaseHas('waypoints', ['project_id' => $own->id, 'user_id' => $this->user->id]);
    }
}
