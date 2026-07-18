<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SurveyPoint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyPointTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
    }

    public function test_can_store_survey_point_with_valid_coordinates(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points",
            [
                'point_name' => 'BM-A',
                'lat'        => -8.12345678,
                'lng'        => 115.12345678,
                'point_type' => 'BM',
                'source'     => 'manual',
            ]
        );

        $response->assertStatus(201);
        $this->assertDatabaseHas('survey_points', [
            'project_id' => $this->project->id,
            'point_name' => 'BM-A',
            'point_type' => 'BM',
        ]);
    }

    public function test_rejects_survey_point_with_lat_out_of_range(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points",
            [
                'point_name' => 'BM-A',
                'lat'        => 99.0,
                'lng'        => 115.0,
                'point_type' => 'BM',
                'source'     => 'manual',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('lat');
    }

    public function test_rejects_survey_point_with_lng_out_of_range(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points",
            [
                'point_name' => 'BM-A',
                'lat'        => -8.0,
                'lng'        => 200.0,
                'point_type' => 'BM',
                'source'     => 'manual',
            ]
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('lng');
    }

    public function test_enforces_unique_point_name_per_project(): void
    {
        $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points",
            ['point_name' => 'BM-A', 'lat' => -8.0, 'lng' => 115.0, 'point_type' => 'BM', 'source' => 'manual']
        );

        $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points",
            ['point_name' => 'BM-A', 'lat' => -8.5, 'lng' => 115.5, 'point_type' => 'BM', 'source' => 'manual']
        );

        $this->assertDatabaseCount('survey_points', 1);
        $this->assertDatabaseHas('survey_points', [
            'project_id' => $this->project->id,
            'point_name' => 'BM-A',
            'lat'        => -8.5,
            'lng'        => 115.5,
        ]);
    }

    public function test_can_update_survey_point_coordinates(): void
    {
        $point = SurveyPoint::factory()->create([
            'project_id' => $this->project->id,
            'point_name' => 'TP-1',
            'lat'        => -8.0,
            'lng'        => 115.0,
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/projects/{$this->project->id}/survey-points/{$point->id}",
            [
                'point_name' => 'TP-1',
                'lat'        => -8.999,
                'lng'        => 115.999,
                'point_type' => 'TP',
                'source'     => 'picker',
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseHas('survey_points', [
            'id'  => $point->id,
            'lat' => -8.999,
            'lng' => 115.999,
        ]);
    }

    public function test_can_delete_survey_point_without_affecting_readings(): void
    {
        $point = SurveyPoint::factory()->create([
            'project_id' => $this->project->id,
            'point_name' => 'TP-1',
        ]);

        $response = $this->actingAs($this->user)->deleteJson(
            "/projects/{$this->project->id}/survey-points/{$point->id}"
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('survey_points', ['id' => $point->id]);
    }

    public function test_can_import_batch_survey_points(): void
    {
        $response = $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points/import",
            [
                'points' => [
                    ['point_name' => 'BM-A', 'lat' => -8.1, 'lng' => 115.1, 'point_type' => 'BM'],
                    ['point_name' => 'TP-1', 'lat' => -8.2, 'lng' => 115.2, 'point_type' => 'TP'],
                ],
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('survey_points', 2);
        $this->assertDatabaseHas('survey_points', ['point_name' => 'BM-A', 'source' => 'gpx']);
        $this->assertDatabaseHas('survey_points', ['point_name' => 'TP-1', 'source' => 'gpx']);
    }

    public function test_import_skips_duplicate_point_names(): void
    {
        SurveyPoint::factory()->create([
            'project_id' => $this->project->id,
            'point_name' => 'BM-A',
            'lat'        => -1.0,
            'lng'        => 1.0,
            'source'     => 'manual',
        ]);

        $response = $this->actingAs($this->user)->postJson(
            "/projects/{$this->project->id}/survey-points/import",
            [
                'points' => [
                    ['point_name' => 'BM-A', 'lat' => -8.1, 'lng' => 115.1, 'point_type' => 'BM'],
                ],
            ]
        );

        $response->assertStatus(200);
        $this->assertDatabaseCount('survey_points', 1);
        $this->assertDatabaseHas('survey_points', [
            'point_name' => 'BM-A',
            'lat'        => -8.1,
            'lng'        => 115.1,
            'source'     => 'gpx',
        ]);
    }

    public function test_cannot_access_other_users_survey_points(): void
    {
        $otherUser = User::factory()->create();
        $otherProject = Project::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)->getJson(
            "/projects/{$otherProject->id}/survey-points"
        );

        $response->assertStatus(403);
    }

    public function test_cannot_update_point_belonging_to_different_project(): void
    {
        $otherProject = Project::factory()->create(['user_id' => $this->user->id]);
        $pointInOtherProject = SurveyPoint::factory()->create([
            'project_id' => $otherProject->id,
            'point_name' => 'BM-X',
        ]);

        $response = $this->actingAs($this->user)->putJson(
            "/projects/{$this->project->id}/survey-points/{$pointInOtherProject->id}",
            ['point_name' => 'BM-X', 'lat' => -8.0, 'lng' => 115.0, 'point_type' => 'BM', 'source' => 'manual']
        );

        $response->assertStatus(404);
    }

    public function test_project_show_includes_survey_points_in_props(): void
    {
        SurveyPoint::factory()->create([
            'project_id' => $this->project->id,
            'point_name' => 'BM-A',
        ]);

        $response = $this->actingAs($this->user)->get("/projects/{$this->project->id}");

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->has('surveyPoints', 1)
        );
    }
}
