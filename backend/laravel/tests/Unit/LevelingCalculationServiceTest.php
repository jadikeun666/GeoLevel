<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Reading;
use App\Services\LevelingCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LevelingCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private LevelingCalculationService $service;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new LevelingCalculationService();

        $this->project = Project::factory()->create([
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
        ]);

        $readings = [
            ['sequence_no' => 1, 'point_name' => 'BM-A', 'reading_type' => 'BS', 'ba' => '1.5230', 'bt' => '1.2100', 'bb' => '0.8970'],
            ['sequence_no' => 2, 'point_name' => 'TP-1', 'reading_type' => 'FS', 'ba' => '1.4870', 'bt' => '1.1750', 'bb' => '0.8630'],
            ['sequence_no' => 3, 'point_name' => 'TP-1', 'reading_type' => 'BS', 'ba' => '1.6210', 'bt' => '1.3050', 'bb' => '0.9890'],
            ['sequence_no' => 4, 'point_name' => 'TP-2', 'reading_type' => 'FS', 'ba' => '1.3940', 'bt' => '1.0820', 'bb' => '0.7700'],
            ['sequence_no' => 5, 'point_name' => 'TP-2', 'reading_type' => 'BS', 'ba' => '1.5560', 'bt' => '1.2430', 'bb' => '0.9300'],
            ['sequence_no' => 6, 'point_name' => 'BM-B', 'reading_type' => 'FS', 'ba' => '1.4120', 'bt' => '1.1000', 'bb' => '0.7880'],
        ];

        foreach ($readings as $data) {
            Reading::factory()->create(array_merge(
                ['project_id' => $this->project->id],
                $data
            ));
        }

        $this->project->load(['readings' => fn ($q) => $q->orderBy('sequence_no')]);
    }

    #[Test]
    public function it_calculates_hi_and_elevations_correctly(): void
    {
        $computed = $this->service->calculateElevations($this->project);

        $this->assertEqualsWithDelta(101.2100, (float) $computed[0]['hi'], 0.0001);
        $this->assertEqualsWithDelta(100.0350, (float) $computed[1]['raw_elevation'], 0.0001);
        $this->assertEqualsWithDelta(101.3400, (float) $computed[2]['hi'], 0.0001);
        $this->assertEqualsWithDelta(100.2580, (float) $computed[3]['raw_elevation'], 0.0001);
        $this->assertEqualsWithDelta(101.5010, (float) $computed[4]['hi'], 0.0001);
        $this->assertEqualsWithDelta(100.4010, (float) $computed[5]['raw_elevation'], 0.0001);
    }

    #[Test]
    public function it_calculates_closure_error_to_6_decimal_places(): void
    {
        $computed = $this->service->calculateElevations($this->project);
        $closure  = $this->service->calculateClosure($this->project, $computed);

        $this->assertEqualsWithDelta(3.7580,   (float) $closure['sum_bs'], 0.000001);
        $this->assertEqualsWithDelta(3.3570,   (float) $closure['sum_fs'], 0.000001);
        $this->assertEqualsWithDelta(0.401000, (float) $closure['fh'],     0.000001);
    }

    #[Test]
    public function it_rejects_survey_when_closure_exceeds_tolerance(): void
    {
        $computed = $this->service->calculateElevations($this->project);
        $closure  = $this->service->calculateClosure($this->project, $computed);

        $this->assertEquals('rejected', $closure['status']);
    }

    #[Test]
    public function it_applies_equal_adjustment_correctly(): void
    {
        $computed = $this->service->calculateElevations($this->project);
        $closure  = $this->service->calculateClosure($this->project, $computed);
        $adjusted = $this->service->applyAdjustment($this->project, $computed, $closure);

        $tp1 = collect($adjusted)->firstWhere('point_name', 'TP-1');
        $this->assertEqualsWithDelta(99.901333, (float) $tp1['adjusted_elevation'], 0.000001);

        $tp2FS = collect($adjusted)->where('point_name', 'TP-2')->firstWhere('reading_type', 'FS');
        $this->assertEqualsWithDelta(99.990667, (float) $tp2FS['adjusted_elevation'], 0.000001);

        $bmb = collect($adjusted)->firstWhere('point_name', 'BM-B');
        $this->assertEqualsWithDelta(100.000000, (float) $bmb['adjusted_elevation'], 0.000001);
    }

    #[Test]
    public function it_persists_computed_elevations_after_recalculate(): void
    {
        $this->service->recalculate($this->project);

        $this->assertDatabaseCount('computed_elevations', 6);
        $this->assertDatabaseHas('computed_elevations', [
            'project_id' => $this->project->id,
            'point_name' => 'BM-B',
        ]);
    }

    #[Test]
    public function it_updates_project_closure_stats_after_recalculate(): void
    {
        $this->service->recalculate($this->project);
        $this->project->refresh();

        $this->assertEqualsWithDelta(0.401000, (float) $this->project->closure_error, 0.000001);
        $this->assertEquals('rejected', $this->project->status);
    }
}
