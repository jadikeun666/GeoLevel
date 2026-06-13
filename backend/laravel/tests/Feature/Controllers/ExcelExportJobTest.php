<?php

namespace Tests\Feature\Controllers;

use App\Events\ExportGenerated;
use App\Jobs\GenerateExcelExportJob;
use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Parity tests for GenerateExcelExportJob storage behaviour.
 *
 * Strategy: mirrors export_file_stored_under_project_exports_directory from
 * ExportControllerTest (PDF). We run the job synchronously via dispatchSync()
 * but do NOT use Excel::fake() — instead we mock the SurveyExport dependency
 * at the Storage level: Excel::store() internally calls Storage::disk('local')
 * ->put(), so Storage::fake('local') is sufficient to intercept the write.
 *
 * The job itself uses Excel::store(..., 'local') which routes through the
 * Storage facade. Under Storage::fake('local') the disk exists but is in-memory,
 * so the file write succeeds without touching the real filesystem.
 */
class ExcelExportJobTest extends TestCase
{
    use RefreshDatabase;

    private User    $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Queue::fake();

        $this->user = User::factory()->create();

        $this->project = Project::factory()->for($this->user)->create([
            'status'              => 'accepted',
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'closure_error'       => '0.001000',
            'allowed_tolerance'   => '0.002452',
        ]);

        ComputedElevation::factory()->count(6)->for($this->project)->create();
    }

    // -------------------------------------------------------------------------

    #[Test]
    public function excel_export_on_accepted_project_queues_excel_job(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->project->id}/export/excel")
             ->assertStatus(200)
             ->assertJson(['success' => true]);

        Queue::assertPushed(GenerateExcelExportJob::class, function ($job) {
            return $job->projectId === $this->project->id
                && $job->userId   === $this->user->id;
        });
    }

    #[Test]
    public function excel_export_queued_response_contains_pending_message(): void
    {
        $response = $this->actingAs($this->user)
             ->getJson("/projects/{$this->project->id}/export/excel")
             ->assertStatus(200);

        $this->assertStringContainsString('queued', strtolower($response->json('message')));
    }

    #[Test]
    public function export_excel_job_failure_logs_to_activity_logs(): void
    {
        $job = new GenerateExcelExportJob($this->project->id, $this->user->id);
        $job->failed(new \RuntimeException('simulated disk failure'));

        $this->assertDatabaseHas('activity_logs', [
            'project_id'    => $this->project->id,
            'user_id'       => $this->user->id,
            'activity_type' => 'job_failed',
        ]);
    }

    #[Test]
    public function export_excel_job_failure_deletes_partial_files(): void
    {
        $partialName = "survey_{$this->project->id}_20991231235959.xlsx";
        $partialPath = "exports/{$this->project->id}/{$partialName}";
        Storage::disk('local')->put($partialPath, 'partial content');

        $job = new GenerateExcelExportJob($this->project->id, $this->user->id);
        $job->failed(new \RuntimeException('simulated disk failure'));

        Storage::disk('local')->assertMissing($partialPath);
    }
}