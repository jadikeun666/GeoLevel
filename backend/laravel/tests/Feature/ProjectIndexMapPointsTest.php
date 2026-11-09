<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SurveyPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectIndexMapPointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function projects_without_survey_points_are_excluded_from_map_points(): void
    {
        Project::factory()->create([
            'user_id' => $this->user->id,
            'name'    => 'Proyek Tanpa Koordinat',
        ]);

        $response = $this->actingAs($this->user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('mapPoints', 0)
        );
    }

    #[Test]
    public function projects_with_survey_points_appear_in_map_points_with_correct_shape(): void
    {
        $project = Project::factory()->create([
            'user_id' => $this->user->id,
            'name'    => 'Proyek Dengan Koordinat',
            'status'  => 'accepted',
        ]);

        SurveyPoint::factory()->create([
            'project_id' => $project->id,
            'point_name' => 'BM-A',
            'lat'        => -6.200000,
            'lng'        => 106.800000,
        ]);

        SurveyPoint::factory()->create([
            'project_id' => $project->id,
            'point_name' => 'TP-1',
            'lat'        => -6.400000,
            'lng'        => 107.000000,
        ]);

        $response = $this->actingAs($this->user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('mapPoints', 1)
            ->where('mapPoints.0.project_id', $project->id)
            ->where('mapPoints.0.project_name', 'Proyek Dengan Koordinat')
            ->where('mapPoints.0.status', 'accepted')
            ->where('mapPoints.0.point_count', 2)
            ->where('mapPoints.0.center_lat', fn ($lat) => abs($lat - (-6.300000)) < 0.000001)
            ->where('mapPoints.0.center_lng', fn ($lng) => abs($lng - 106.900000) < 0.000001)
        );
    }

    #[Test]
    public function map_points_only_include_current_users_projects(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);
        SurveyPoint::factory()->create(['project_id' => $otherProject->id]);

        $response = $this->actingAs($this->user)->get(route('projects.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Projects/Index')
            ->has('mapPoints', 0)
        );
    }
}
