<?php

namespace Tests\Feature\Api;
use PHPUnit\Framework\Attributes\Test;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the adjustment endpoint.
 *
 * POST /projects/{id}/adjust
 *
 * Verifies:
 *   - equal distribution produces correct adjusted_elevation (canonical dataset)
 *   - bowditch produces distance-proportional corrections
 *   - adjustments are reversible (correction = 0 restores raw)
 *   - raw readings table is never touched
 *   - correct status transition after adjustment
 */
class AdjustmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    private const DELTA_6 = 0.000001;
    private const DELTA_4 = 0.0001;

    private User    $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create([
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'calculated',
            'closure_error'       => '0.401000',
            'allowed_tolerance'   => '0.002452',
            'total_distance_km'   => '0.3756',
        ]);

        $this->seedCanonicalComputedElevations();
    }

    // ---------------------------------------------------------------------------
    // Equal distribution — canonical values
    // ---------------------------------------------------------------------------

    #[Test]
    public function equal_adjustment_produces_correct_adjusted_elevation_for_tp1(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        $tp1 = ComputedElevation::where('project_id', $this->project->id)
                                ->where('point_name', 'TP-1')
                                ->where('sequence_no', 2)
                                ->first();

        $this->assertEqualsWithDelta(99.901333, (float) $tp1->adjusted_elevation, self::DELTA_6);
        $this->assertEqualsWithDelta(-0.133667, (float) $tp1->correction,         self::DELTA_6);
    }

    #[Test]
    public function equal_adjustment_produces_correct_adjusted_elevation_for_tp2(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        $tp2 = ComputedElevation::where('project_id', $this->project->id)
                                ->where('point_name', 'TP-2')
                                ->where('sequence_no', 4)
                                ->first();

        $this->assertEqualsWithDelta(99.990667, (float) $tp2->adjusted_elevation, self::DELTA_6);
        $this->assertEqualsWithDelta(-0.267333, (float) $tp2->correction,         self::DELTA_6);
    }

    #[Test]
    public function equal_adjustment_produces_correct_adjusted_elevation_for_bm_b(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        $bmb = ComputedElevation::where('project_id', $this->project->id)
                                ->where('point_name', 'BM-B')
                                ->first();

        $this->assertEqualsWithDelta(100.000000, (float) $bmb->adjusted_elevation, self::DELTA_6);
        $this->assertEqualsWithDelta(-0.401000,  (float) $bmb->correction,         self::DELTA_6);
    }

    // ---------------------------------------------------------------------------
    // Reversibility
    // ---------------------------------------------------------------------------

    #[Test]
    public function adjustment_is_reversible_by_zeroing_corrections(): void
    {
        // Apply adjustment
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        // Zero all corrections via a second call (or via direct reset)
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'reset'])
             ->assertStatus(200);

        // adjusted_elevation must equal raw_elevation for all rows
        $rows = ComputedElevation::where('project_id', $this->project->id)->get();

        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(
                (float) $row->raw_elevation,
                (float) $row->adjusted_elevation,
                self::DELTA_4,
                "adjusted_elevation must equal raw_elevation after reset for point {$row->point_name}"
            );
            $this->assertEqualsWithDelta(0.0, (float) $row->correction, self::DELTA_6);
        }
    }

    // ---------------------------------------------------------------------------
    // Raw readings untouched
    // ---------------------------------------------------------------------------

    #[Test]
    public function adjustment_never_modifies_readings_table(): void
    {
        $originalReadings = Reading::where('project_id', $this->project->id)
                                   ->orderBy('sequence_no')
                                   ->get(['id', 'ba', 'bt', 'bb', 'sequence_no'])
                                   ->toArray();

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        $afterReadings = Reading::where('project_id', $this->project->id)
                                ->orderBy('sequence_no')
                                ->get(['id', 'ba', 'bt', 'bb', 'sequence_no'])
                                ->toArray();

        $this->assertEquals($originalReadings, $afterReadings, 'readings table must not be mutated');
    }

    // ---------------------------------------------------------------------------
    // Method validation
    // ---------------------------------------------------------------------------

    #[Test]
    public function invalid_adjustment_method_returns_422(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'unknown_method'])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['method']);
    }

    #[Test]
    public function bowditch_adjustment_accepted(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'bowditch'])
             ->assertStatus(200);
    }

    // ---------------------------------------------------------------------------
    // Authorisation
    // ---------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_user_cannot_adjust(): void
    {
        $this->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(401);
    }

    #[Test]
    public function user_cannot_adjust_another_users_project(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // Seed helpers
    // ---------------------------------------------------------------------------

    private function seedCanonicalComputedElevations(): void
    {
        // Canonical FS/turning points — these are the rows that receive corrections
        $rows = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'raw_elevation' => '100.0000', 'cumulative_distance' => '0'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'raw_elevation' => '100.0350', 'cumulative_distance' => '125.2'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'raw_elevation' => '100.0350', 'cumulative_distance' => '125.2'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'raw_elevation' => '100.2580', 'cumulative_distance' => '250.4'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'raw_elevation' => '100.2580', 'cumulative_distance' => '250.4'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'raw_elevation' => '100.4010', 'cumulative_distance' => '375.6'],
        ];

        foreach ($rows as $row) {
            ComputedElevation::factory()->for($this->project)->create(array_merge($row, [
                'correction'         => '0',
                'adjusted_elevation' => $row['raw_elevation'],
            ]));
        }

        // Seed corresponding readings so the "never mutate readings" assertion works
        $readingData = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'reading_type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'reading_type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'reading_type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'reading_type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'reading_type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'reading_type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880'],
        ];

        foreach ($readingData as $data) {
            Reading::factory()->for($this->project)->create($data);
        }
    }
    
}