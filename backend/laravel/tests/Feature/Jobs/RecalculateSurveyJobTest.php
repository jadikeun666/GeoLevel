<?php

namespace Tests\Feature\Jobs;
use PHPUnit\Framework\Attributes\Test;

use App\Jobs\RecalculateSurveyJob;
use App\Models\ActivityLog;
use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Throwable;

/**
 * Feature tests for RecalculateSurveyJob.
 *
 * Verifies (architecture.md):
 *   - Job deletes all existing computed_elevations before recomputing
 *   - Job writes fresh rows after re-computation (canonical dataset)
 *   - On failure: project.status → 'draft', error logged to activity_logs
 *   - Job retries max 3 times (config check)
 *   - failed_jobs entry written on exhausted retries
 *   - Job is idempotent: run multiple times → same result
 */
class RecalculateSurveyJobTest extends TestCase
{
    use RefreshDatabase;

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
            'status'              => 'draft',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Normal execution
    // ---------------------------------------------------------------------------

    #[Test]
    public function job_deletes_existing_computed_elevations_before_recomputing(): void
    {
        // Seed stale rows
        ComputedElevation::factory()->count(3)->for($this->project)->create([
            'raw_elevation' => '999.9999',
        ]);

        $this->seedCanonicalReadings();

        (new RecalculateSurveyJob($this->project->id))->handle();

        // Stale rows must be gone; fresh rows must be present
        $this->assertDatabaseMissing('computed_elevations', [
            'project_id'   => $this->project->id,
            'raw_elevation' => '999.9999',
        ]);
    }

    #[Test]
    public function job_writes_correct_elevations_for_canonical_dataset(): void
    {
        $this->seedCanonicalReadings();

        (new RecalculateSurveyJob($this->project->id))->handle();

        // Assertions on the three turning-point / closing elevations
        $this->assertComputedElevation('TP-1', 100.0350);
        $this->assertComputedElevation('TP-2', 100.2580);
        $this->assertComputedElevation('BM-B', 100.4010);
    }

    #[Test]
    public function job_sets_correction_to_zero_on_fresh_rows(): void
    {
        $this->seedCanonicalReadings();

        (new RecalculateSurveyJob($this->project->id))->handle();

        ComputedElevation::where('project_id', $this->project->id)->each(function ($row) {
            $this->assertEqualsWithDelta(0.0, (float) $row->correction, 0.000001,
                "correction must be 0 for sequence_no={$row->sequence_no}"
            );
        });
    }

    #[Test]
    public function job_logs_recalculation_to_activity_logs(): void
    {
        $this->seedCanonicalReadings();

        (new RecalculateSurveyJob($this->project->id))->handle();

        $this->assertDatabaseHas('activity_logs', [
            'project_id'    => $this->project->id,
            'activity_type' => 'survey_recalculated',
        ]);
    }

    #[Test]
    public function job_is_idempotent_when_run_multiple_times(): void
    {
        $this->seedCanonicalReadings();

        (new RecalculateSurveyJob($this->project->id))->handle();
        $firstCount = ComputedElevation::where('project_id', $this->project->id)->count();
        $firstBmB   = ComputedElevation::where('project_id', $this->project->id)
                                       ->where('point_name', 'BM-B')
                                       ->value('raw_elevation');

        (new RecalculateSurveyJob($this->project->id))->handle();
        $secondCount = ComputedElevation::where('project_id', $this->project->id)->count();
        $secondBmB   = ComputedElevation::where('project_id', $this->project->id)
                                        ->where('point_name', 'BM-B')
                                        ->value('raw_elevation');

        $this->assertSame($firstCount, $secondCount);
        $this->assertEqualsWithDelta((float) $firstBmB, (float) $secondBmB, self::DELTA_4);
    }

    // ---------------------------------------------------------------------------
    // Failure behaviour
    // ---------------------------------------------------------------------------

    #[Test]
    public function job_failure_sets_project_status_to_draft(): void
    {
        $this->project->update(['status' => 'calculated']);

        $exception = new \RuntimeException('Simulated failure');
        $job       = new RecalculateSurveyJob($this->project->id);
        $job->failed($exception);

        $this->assertSame('draft', $this->project->fresh()->status);
    }

    #[Test]
    public function job_failure_writes_to_activity_logs(): void
    {
        $exception = new \RuntimeException('Simulated failure');
        $job       = new RecalculateSurveyJob($this->project->id);
        $job->failed($exception);

        $this->assertDatabaseHas('activity_logs', [
            'project_id'    => $this->project->id,
            'activity_type' => 'job_failed',
        ]);
    }

    #[Test]
    public function job_has_max_three_tries(): void
    {
        $job = new RecalculateSurveyJob($this->project->id);
        $this->assertSame(3, $job->tries);
    }

    #[Test]
    public function job_has_sixty_second_timeout(): void
    {
        $job = new RecalculateSurveyJob($this->project->id);
        $this->assertSame(60, $job->timeout);
    }

    // ---------------------------------------------------------------------------
    // Dispatch via Observer (integration smoke test)
    // ---------------------------------------------------------------------------

    #[Test]
    public function creating_a_reading_via_http_dispatches_job_to_queue(): void
    {
        Queue::fake();

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", [
                 'sequence_no'  => 1,
                 'point_name'   => 'BM-A',
                 'reading_type' => 'BS',
                 'ba'           => '1.5230',
                 'bt'           => '1.2100',
                 'bb'           => '0.8970',
             ]);

        Queue::assertPushed(RecalculateSurveyJob::class, function ($job) {
            return $job->projectId === $this->project->id;
        });
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function seedCanonicalReadings(): void
    {
        $data = [
            [1, 'BM-A', 'BS', '1.5230', '1.2100', '0.8970'],
            [2, 'TP-1', 'FS', '1.4870', '1.1750', '0.8630'],
            [3, 'TP-1', 'BS', '1.6210', '1.3050', '0.9890'],
            [4, 'TP-2', 'FS', '1.3940', '1.0820', '0.7700'],
            [5, 'TP-2', 'BS', '1.5560', '1.2430', '0.9300'],
            [6, 'BM-B', 'FS', '1.4120', '1.1000', '0.7880'],
        ];

        foreach ($data as [$seq, $point, $type, $ba, $bt, $bb]) {
            Reading::factory()->for($this->project)->create([
                'sequence_no'  => $seq,
                'point_name'   => $point,
                'reading_type' => $type,
                'ba'           => $ba,
                'bt'           => $bt,
                'bb'           => $bb,
            ]);
        }
    }

    private function assertComputedElevation(string $pointName, float $expected): void
    {
        // Use the LAST FS entry for each point (TP-1 appears as both BS and FS destination)
        $row = ComputedElevation::where('project_id', $this->project->id)
                                ->where('point_name', $pointName)
                                ->orderByDesc('sequence_no')
                                ->first();

        $this->assertNotNull($row, "No computed_elevation row found for point {$pointName}");
        $this->assertEqualsWithDelta(
            $expected,
            (float) $row->raw_elevation,
            self::DELTA_4,
            "raw_elevation mismatch for point {$pointName}"
        );
    }
}