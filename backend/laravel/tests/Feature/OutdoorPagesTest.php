<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Track;
use App\Models\User;
use App\Models\Waypoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutdoorPagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $track = Track::factory()->create();

        $this->get(route('outdoor.index'))->assertRedirect(route('login'));
        $this->get(route('outdoor.tracks.show', $track))->assertRedirect(route('login'));
    }

    #[Test]
    public function index_renders_only_own_tracks_waypoints_and_projects(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Project::factory()->create(['user_id' => $user->id]);
        Project::factory()->create(['user_id' => $other->id]);
        Track::factory()->create(['user_id' => $user->id]);
        Track::factory()->create(['user_id' => $other->id]);
        Waypoint::factory()->count(2)->create(['user_id' => $user->id]);
        Waypoint::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)->get(route('outdoor.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Outdoor/Index')
                ->has('tracks', 1)
                ->has('waypoints', 2)
                ->has('projects', 1)
            );
    }

    #[Test]
    public function show_renders_own_track(): void
    {
        $user  = User::factory()->create();
        $track = Track::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get(route('outdoor.tracks.show', $track))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Outdoor/Show')
                ->where('track.id', $track->id)
            );
    }

    #[Test]
    public function show_is_forbidden_for_other_users(): void
    {
        $track = Track::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('outdoor.tracks.show', $track))
            ->assertForbidden();
    }
}
