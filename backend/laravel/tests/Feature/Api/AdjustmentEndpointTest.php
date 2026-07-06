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
 * Uses the canonical dataset from docs/formulas.md seeded directly into
 * computed_elevations. Readings are inserted with withoutEvents() so
 * ReadingObserver / RecalculateSurveyJob does NOT fire and overwrite the
 * carefully seeded raw_elevation values.
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

        $this->seedCanonicalData();
    }

    // -------------------------------------------------------------------------
    // Equal distribution — canonical values from docs/formulas.md
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Reversibility
    // -------------------------------------------------------------------------

    #[Test]
    public function adjustment_is_reversible_by_zeroing_corrections(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'equal'])
             ->assertStatus(200);

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/adjust", ['method' => 'reset'])
             ->assertStatus(200);

        $rows = ComputedElevation::where('project_id', $this->project->id)->get();

        foreach ($rows as $row) {
            $this->assertEqualsWithDelta(
                (float) $row->raw_elevation,
                (float) $row->adjusted_elevation,
                self::DELTA_4,
                "adjusted_elevation must equal raw_elevation after reset for {$row->point_name}"
            );
            $this->assertEqualsWithDelta(0.0, (float) $row->correction, self::DELTA_6);
        }
    }

    // -------------------------------------------------------------------------
    // Raw readings untouched
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Authorisation
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Seed helpers
    // -------------------------------------------------------------------------

    /**
     * Seed canonical readings + computed_elevations from docs/formulas.md.
     *
     * Order is critical:
     *   1. Readings inserted first (withoutEvents) → provides valid reading_id FKs.
     *   2. ComputedElevations inserted second, each referencing its paired reading.
     *
     * withoutEvents() prevents ReadingObserver from firing RecalculateSurveyJob,
     * which would wipe and rebuild computed_elevations from scratch and replace
     * these carefully seeded canonical values.
     */
    private function seedCanonicalData(): void
    {
        $readingDefs = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'reading_type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'reading_type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'reading_type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'reading_type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'reading_type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'reading_type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880'],
        ];

        $elevationDefs = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'raw_elevation' => '100.0000', 'cumulative_distance' => '0.000'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'raw_elevation' => '100.0350', 'cumulative_distance' => '125.200'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'raw_elevation' => '100.0350', 'cumulative_distance' => '125.200'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'raw_elevation' => '100.2580', 'cumulative_distance' => '250.400'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'raw_elevation' => '100.2580', 'cumulative_distance' => '250.400'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'raw_elevation' => '100.4010', 'cumulative_distance' => '375.600'],
        ];

        // Step 1: insert readings without triggering the observer.
        $readingIds = [];
        Reading::withoutEvents(function () use ($readingDefs, &$readingIds) {
            foreach ($readingDefs as $def) {
                $reading = Reading::factory()->for($this->project)->create($def);
                $readingIds[$def['sequence_no']] = $reading->id;
            }
        });

        // Step 2: insert computed_elevations referencing the real reading IDs.
        // No observer concern here — ComputedElevation has no observer.
        foreach ($elevationDefs as $def) {
            ComputedElevation::create([
                'project_id'          => $this->project->id,
                'reading_id'          => $readingIds[$def['sequence_no']],
                'sequence_no'         => $def['sequence_no'],
                'point_name'          => $def['point_name'],
                'hi'                  => null,
                'raw_elevation'       => $def['raw_elevation'],
                'correction'          => '0.000000',
                'adjusted_elevation'  => $def['raw_elevation'],
                'cumulative_distance' => $def['cumulative_distance'],
            ]);
        }
    }
}
