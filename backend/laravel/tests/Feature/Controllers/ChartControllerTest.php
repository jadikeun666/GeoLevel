<?php

namespace Tests\Feature\Controllers;

use PHPUnit\Framework\Attributes\Test;

use App\Models\ComputedElevation;
use App\Models\CrossSection;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for chart endpoints (VisualizationService via ChartController).
 *
 * Contracts (architecture.md):
 *   GET /projects/{id}/chart/longsection
 *     -> { data: [{ x, y, label }], title }
 *
 *   GET /projects/{id}/chart/crosssection[?station=...]
 *     -> { data: { station, offsets: [{ side, distance, elevation }] } | null, stations: [] }
 */
class ChartControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::factory()->for($this->user)->create([
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'calculated',
            'closure_error'       => '0.001000',
            'allowed_tolerance'   => '0.002452',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Long section
    // ---------------------------------------------------------------------------

    #[Test]
    public function long_section_returns_ordered_points_with_correct_shape(): void
    {
        ComputedElevation::factory()->for($this->project)->create([
            'sequence_no'         => 1,
            'point_name'          => 'BM-A',
            'cumulative_distance' => '0.000',
            'adjusted_elevation'  => '100.0000',
        ]);
        ComputedElevation::factory()->for($this->project)->create([
            'sequence_no'         => 2,
            'point_name'          => 'TP-1',
            'cumulative_distance' => '125.200',
            'adjusted_elevation'  => '99.9013',
        ]);
        ComputedElevation::factory()->for($this->project)->create([
            'sequence_no'         => 3,
            'point_name'          => 'TP-2',
            'cumulative_distance' => '250.400',
            'adjusted_elevation'  => '99.9907',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/longsection")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    ['x', 'y', 'label'],
                ],
                'title',
            ]);

        $data = $response->json('data');

        $this->assertCount(3, $data);

        // Ordered by sequence_no
        $this->assertSame('BM-A', $data[0]['label']);
        $this->assertSame('TP-1', $data[1]['label']);
        $this->assertSame('TP-2', $data[2]['label']);

        // Values cast to float, correct mapping
        $this->assertEqualsWithDelta(0.000,   $data[0]['x'], 0.0001);
        $this->assertEqualsWithDelta(100.0000, $data[0]['y'], 0.0001);

        $this->assertEqualsWithDelta(125.200, $data[1]['x'], 0.0001);
        $this->assertEqualsWithDelta(99.9013, $data[1]['y'], 0.0001);

        $this->assertStringContainsString($this->project->name, $response->json('title'));
    }

    #[Test]
    public function long_section_returns_empty_array_when_no_elevations(): void
    {
        $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/longsection")
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }

    #[Test]
    public function long_section_unauthenticated_returns_401(): void
    {
        $this->getJson("/projects/{$this->project->id}/chart/longsection")
            ->assertStatus(401);
    }

    #[Test]
    public function long_section_other_users_project_is_forbidden(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->getJson("/projects/{$this->project->id}/chart/longsection")
            ->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // Cross section
    // ---------------------------------------------------------------------------

    #[Test]
    public function cross_section_with_station_param_returns_sorted_offsets(): void
    {
        CrossSection::factory()->for($this->project)->create([
            'station_name'     => 'STA 0+000',
            'station_distance' => '0.000',
            'offsets'          => [
                ['side' => 'C', 'distance' => 0,   'elevation' => 100.000],
                ['side' => 'R', 'distance' => 3.0, 'elevation' => 99.500],
                ['side' => 'L', 'distance' => 3.0, 'elevation' => 99.700],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/crosssection?station=STA+0%2B000")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['station', 'offsets' => [['side', 'distance', 'elevation']]],
                'stations',
            ]);

        $offsets = $response->json('data.offsets');

        // Sorted: L (negative distance) first, then C, then R
        $this->assertSame('L', $offsets[0]['side']);
        $this->assertSame('C', $offsets[1]['side']);
        $this->assertSame('R', $offsets[2]['side']);

        $this->assertSame('STA 0+000', $response->json('data.station'));
        $this->assertContains('STA 0+000', $response->json('stations'));
    }

    #[Test]
    public function cross_section_without_station_param_returns_first_station(): void
    {
        CrossSection::factory()->for($this->project)->create([
            'station_name'     => 'STA 0+000',
            'station_distance' => '0.000',
            'offsets'          => [
                ['side' => 'C', 'distance' => 0, 'elevation' => 100.000],
            ],
        ]);
        CrossSection::factory()->for($this->project)->create([
            'station_name'     => 'STA 0+050',
            'station_distance' => '50.000',
            'offsets'          => [
                ['side' => 'C', 'distance' => 0, 'elevation' => 99.800],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/crosssection")
            ->assertStatus(200);

        // First by station_distance ascending
        $this->assertSame('STA 0+000', $response->json('data.station'));
        $this->assertSame(['STA 0+000', 'STA 0+050'], $response->json('stations'));
    }

    #[Test]
    public function cross_section_returns_null_data_when_no_cross_sections_exist(): void
    {
        $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/crosssection")
            ->assertStatus(200)
            ->assertJson(['data' => null, 'stations' => []]);
    }

    #[Test]
    public function cross_section_with_unknown_station_returns_404(): void
    {
        CrossSection::factory()->for($this->project)->create([
            'station_name'     => 'STA 0+000',
            'station_distance' => '0.000',
            'offsets'          => [
                ['side' => 'C', 'distance' => 0, 'elevation' => 100.000],
            ],
        ]);

        $this->actingAs($this->user)
            ->getJson("/projects/{$this->project->id}/chart/crosssection?station=STA+9%2B999")
            ->assertStatus(404)
            ->assertJson(['error' => 'Stasiun tidak ditemukan']);
    }

    #[Test]
    public function cross_section_unauthenticated_returns_401(): void
    {
        $this->getJson("/projects/{$this->project->id}/chart/crosssection")
            ->assertStatus(401);
    }

    #[Test]
    public function cross_section_other_users_project_is_forbidden(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->getJson("/projects/{$this->project->id}/chart/crosssection")
            ->assertStatus(403);
    }
}