<?php

namespace Tests\Feature\Controllers;
use PHPUnit\Framework\Attributes\Test;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\User;
use App\Jobs\GenerateExcelExportJob;
use App\Jobs\GeneratePdfExportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for export endpoints.
 *
 * Business rules verified (from engineering-rules.md / exports.md):
 *   - Export only allowed when project.status = 'accepted'
 *   - Jobs are queued (never run synchronously)
 *   - Correct API response shape for pending, forbidden, and available states
 *   - Output stored under storage/app/exports/{project_id}/
 *   - CSV, Excel, PDF endpoints each guard the status
 */
class ExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User    $user;
    private Project $acceptedProject;
    private Project $draftProject;
    private Project $calculatedProject;
    private Project $rejectedProject;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake('local');

        $this->user = User::factory()->create();

        $this->acceptedProject   = $this->projectWithStatus('accepted');
        $this->draftProject      = $this->projectWithStatus('draft');
        $this->calculatedProject = $this->projectWithStatus('calculated');
        $this->rejectedProject   = $this->projectWithStatus('rejected');
    }

    // ---------------------------------------------------------------------------
    // PDF export
    // ---------------------------------------------------------------------------

    #[Test]
    public function pdf_export_on_accepted_project_queues_job_and_returns_200(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->acceptedProject->id}/export/pdf")
             ->assertStatus(200)
             ->assertJson(['success' => true]);

        Queue::assertPushed(GeneratePdfExportJob::class);
    }

    #[Test]
    public function pdf_export_on_draft_project_returns_403_with_correct_message(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->draftProject->id}/export/pdf")
             ->assertStatus(403)
             ->assertJson([
                 'success' => false,
                 'message' => 'Export not allowed. Survey status must be accepted.',
             ]);

        Queue::assertNotPushed(GeneratePdfExportJob::class);
    }

    #[Test]
    public function pdf_export_on_calculated_project_is_forbidden(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->calculatedProject->id}/export/pdf")
             ->assertStatus(403);
    }

    #[Test]
    public function pdf_export_on_rejected_project_is_forbidden(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->rejectedProject->id}/export/pdf")
             ->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // Excel export
    // ---------------------------------------------------------------------------

    #[Test]
    public function excel_export_on_accepted_project_queues_job_and_returns_200(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->acceptedProject->id}/export/excel")
             ->assertStatus(200)
             ->assertJson(['success' => true]);

        Queue::assertPushed(GenerateExcelExportJob::class);
    }

    #[Test]
    public function excel_export_on_non_accepted_project_returns_403(): void
    {
        foreach ([$this->draftProject, $this->calculatedProject, $this->rejectedProject] as $project) {
            $this->actingAs($this->user)
                 ->getJson("/projects/{$project->id}/export/excel")
                 ->assertStatus(403, "Expected 403 for status={$project->status}");
        }

        Queue::assertNotPushed(GenerateExcelExportJob::class);
    }

    // ---------------------------------------------------------------------------
    // CSV export
    // ---------------------------------------------------------------------------

    #[Test]
    public function csv_export_on_accepted_project_returns_200_with_file_url(): void
    {
        // CSV is generated synchronously (lightweight) but still queued per architecture
        $response = $this->actingAs($this->user)
                         ->getJson("/projects/{$this->acceptedProject->id}/export/csv")
                         ->assertStatus(200)
                         ->assertJson(['success' => true]);

        // Response must contain a URL to the export file
        $this->assertNotEmpty($response->json('data.url'));
    }

    #[Test]
    public function csv_export_on_non_accepted_project_returns_403(): void
    {
        $this->actingAs($this->user)
             ->getJson("/projects/{$this->draftProject->id}/export/csv")
             ->assertStatus(403)
             ->assertJson(['success' => false]);
    }

    // ---------------------------------------------------------------------------
    // Queued response shape
    // ---------------------------------------------------------------------------

    #[Test]
    public function queued_export_returns_pending_message(): void
    {
        $response = $this->actingAs($this->user)
                         ->getJson("/projects/{$this->acceptedProject->id}/export/pdf")
                         ->assertStatus(200);

        $this->assertStringContainsString('queued', strtolower($response->json('message')));
    }

    // ---------------------------------------------------------------------------
    // File storage path
    // ---------------------------------------------------------------------------

    #[Test]
    public function export_file_stored_under_project_exports_directory(): void
    {
        // Simulate job execution to verify storage path
        $job = new GeneratePdfExportJob($this->acceptedProject->id, $this->user->id);
        $job->handle();

        $expectedDir = "exports/{$this->acceptedProject->id}";
        $files = Storage::disk('local')->files($expectedDir);

        $this->assertNotEmpty($files, "Expected export file in {$expectedDir}");
    }

    // ---------------------------------------------------------------------------
    // Authorisation
    // ---------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson("/projects/{$this->acceptedProject->id}/export/pdf")
             ->assertStatus(401);
    }

    #[Test]
    public function user_cannot_export_another_users_project(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
             ->getJson("/projects/{$this->acceptedProject->id}/export/pdf")
             ->assertStatus(403);
    }



    // ---------------------------------------------------------------------------
    // Helper
    // ---------------------------------------------------------------------------

    private function projectWithStatus(string $status): Project
    {
        $project = Project::factory()->for($this->user)->create([
            'status'              => $status,
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'closure_error'       => $status === 'accepted' ? '0.001000' : null,
            'allowed_tolerance'   => $status === 'accepted' ? '0.002452' : null,
        ]);

        // Seed minimal computed elevations so export has data to work with
        if ($status === 'accepted') {
            ComputedElevation::factory()->count(6)->for($project)->create();
        }

        return $project;
    }
}