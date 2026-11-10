<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SurveyPoint;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WaypointControllerTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Pos A', 'lat' => -6.2, 'lng' => 106.8], $overrides);
    }

    #[Test]
    public function guest_cannot_access_waypoints(): void
    {
        $this->getJson(route('outdoor.waypoints.index'))->assertUnauthorized();
    }

    #[Test]
    public function store_creates_waypoint_owned_by_current_user(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['user_id' => $this->other->id]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Pos A');

        $this->assertDatabaseHas('waypoints', ['name' => 'Pos A', 'user_id' => $this->user->id]);
    }

    #[Test]
    public function store_rejects_out_of_range_coordinates(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['lat' => 91, 'lng' => 181]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    #[Test]
    public function store_rejects_unknown_category(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['category' => 'bogus']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    #[Test]
    public function store_accepts_own_project_and_rejects_foreign_project(): void
    {
        $own     = Project::factory()->create(['user_id' => $this->user->id]);
        $foreign = Project::factory()->create(['user_id' => $this->other->id]);

        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['project_id' => $own->id]))
            ->assertCreated()
            ->assertJsonPath('data.project_id', $own->id);

        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['project_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['project_id']);
    }

    #[Test]
    public function store_links_only_own_survey_point(): void
    {
        $own     = SurveyPoint::factory()->create([
            'project_id' => Project::factory()->create(['user_id' => $this->user->id])->id,
        ]);
        $foreign = SurveyPoint::factory()->create([
            'project_id' => Project::factory()->create(['user_id' => $this->other->id])->id,
        ]);

        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['survey_point_id' => $own->id]))
            ->assertCreated();

        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload(['survey_point_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['survey_point_id']);
    }

    #[Test]
    public function store_returns_422_when_per_user_limit_reached(): void
    {
        config(['geolevel.outdoor.waypoint_max_per_user' => 1]);
        Waypoint::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->postJson(route('outdoor.waypoints.store'), $this->payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function index_returns_only_own_waypoints_and_filters_by_project(): void
    {
        $project = Project::factory()->create(['user_id' => $this->user->id]);
        Waypoint::factory()->create(['user_id' => $this->user->id, 'project_id' => $project->id]);
        Waypoint::factory()->create(['user_id' => $this->user->id]);
        Waypoint::factory()->create(['user_id' => $this->other->id]);

        $this->actingAs($this->user)
            ->getJson(route('outdoor.waypoints.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($this->user)
            ->getJson(route('outdoor.waypoints.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function owner_can_update_and_other_user_gets_403(): void
    {
        $waypoint = Waypoint::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->putJson(route('outdoor.waypoints.update', $waypoint), $this->payload(['name' => 'Baru']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru');

        $this->actingAs($this->other)
            ->putJson(route('outdoor.waypoints.update', $waypoint), $this->payload(['name' => 'Retas']))
            ->assertForbidden();

        $this->assertSame('Baru', $waypoint->fresh()->name);
    }

    #[Test]
    public function owner_can_delete_and_other_user_gets_403(): void
    {
        $waypoint = Waypoint::factory()->create(['user_id' => $this->user->id]);

        $this->actingAs($this->other)
            ->deleteJson(route('outdoor.waypoints.destroy', $waypoint))
            ->assertForbidden();
        $this->assertDatabaseHas('waypoints', ['id' => $waypoint->id]);

        $this->actingAs($this->user)
            ->deleteJson(route('outdoor.waypoints.destroy', $waypoint))
            ->assertOk();
        $this->assertDatabaseMissing('waypoints', ['id' => $waypoint->id]);
    }

    #[Test]
    public function item_linked_to_foreign_project_is_not_accessible_even_to_its_owner(): void
    {
        $foreign  = Project::factory()->create(['user_id' => $this->other->id]);
        $waypoint = Waypoint::factory()->create(['user_id' => $this->user->id, 'project_id' => $foreign->id]);

        $this->actingAs($this->user)
            ->putJson(route('outdoor.waypoints.update', $waypoint), $this->payload())
            ->assertForbidden();
    }
}
