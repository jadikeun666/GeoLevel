<?php

namespace Tests\Feature\Controllers;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests untuk export endpoints (synchronous download).
 *
 * Controller baru mengembalikan file langsung — tidak ada queue, tidak ada JSON.
 * PDF   → Response (Content-Type: application/pdf)
 * Excel → BinaryFileResponse (Content-Type: spreadsheet)
 * CSV   → StreamedResponse (Content-Type: text/csv)
 * 403   → abort(403) ketika status bukan accepted
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
    public function pdf_export_on_accepted_project_returns_200_with_pdf(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'pdf',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    #[Test]
    public function pdf_export_response_has_attachment_disposition(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/pdf");

        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.pdf', $disposition);
    }

    #[Test]
    public function pdf_export_on_draft_project_returns_403(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->draftProject->id}/export/pdf")
             ->assertStatus(403);
    }

    #[Test]
    public function pdf_export_on_calculated_project_is_forbidden(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->calculatedProject->id}/export/pdf")
             ->assertStatus(403);
    }

    #[Test]
    public function pdf_export_on_rejected_project_is_forbidden(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->rejectedProject->id}/export/pdf")
             ->assertStatus(403);
    }

    #[Test]
    public function pdf_export_does_not_push_any_job(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/pdf");

        Queue::assertNothingPushed();
    }

    #[Test]
    public function pdf_export_logs_activity(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/pdf");

        $this->assertDatabaseHas('activity_logs', [
            'project_id'    => $this->acceptedProject->id,
            'user_id'       => $this->user->id,
            'activity_type' => 'export_generated',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Excel export
    // ---------------------------------------------------------------------------

    #[Test]
    public function excel_export_on_accepted_project_returns_200_with_file(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/excel");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    #[Test]
    public function excel_export_on_non_accepted_project_returns_403(): void
    {
        foreach ([$this->draftProject, $this->calculatedProject, $this->rejectedProject] as $project) {
            $this->actingAs($this->user)
                 ->get("/projects/{$project->id}/export/excel")
                 ->assertStatus(403);
        }
    }

    // ---------------------------------------------------------------------------
    // CSV export
    // ---------------------------------------------------------------------------

    #[Test]
    public function csv_export_on_accepted_project_returns_200_with_csv(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/csv");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'text/csv',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    #[Test]
    public function csv_export_response_has_attachment_disposition(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->acceptedProject->id}/export/csv");

        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.csv', $disposition);
    }

    #[Test]
    public function csv_export_on_non_accepted_project_returns_403(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->draftProject->id}/export/csv")
             ->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // No queue
    // ---------------------------------------------------------------------------

    #[Test]
    public function export_endpoints_never_push_jobs_to_queue(): void
    {
        $id = $this->acceptedProject->id;

        $this->actingAs($this->user)->get("/projects/{$id}/export/pdf");
        $this->actingAs($this->user)->get("/projects/{$id}/export/excel");
        $this->actingAs($this->user)->get("/projects/{$id}/export/csv");

        Queue::assertNothingPushed();
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
             ->get("/projects/{$this->acceptedProject->id}/export/pdf")
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

        if ($status === 'accepted') {
            ComputedElevation::factory()->count(6)->for($project)->create();
        }

        return $project;
    }
}