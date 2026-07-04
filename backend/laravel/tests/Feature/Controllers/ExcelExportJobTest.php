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
 * Tests untuk Excel export endpoint (synchronous download).
 *
 * Controller baru mengembalikan file langsung via Excel::download() —
 * tidak ada job yang di-dispatch, tidak ada queue.
 * Response adalah BinaryFileResponse (Content-Type: spreadsheet).
 */
class ExcelExportJobTest extends TestCase
{
    use RefreshDatabase;

    private User    $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

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
    public function excel_export_on_accepted_project_returns_200_with_file(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->project->id}/export/excel");

        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    #[Test]
    public function excel_export_response_has_attachment_disposition(): void
    {
        $response = $this->actingAs($this->user)
             ->get("/projects/{$this->project->id}/export/excel");

        $disposition = $response->headers->get('Content-Disposition') ?? '';
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    #[Test]
    public function excel_export_does_not_push_any_job(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->project->id}/export/excel");

        Queue::assertNothingPushed();
    }

    #[Test]
    public function excel_export_logs_activity(): void
    {
        $this->actingAs($this->user)
             ->get("/projects/{$this->project->id}/export/excel");

        $this->assertDatabaseHas('activity_logs', [
            'project_id'    => $this->project->id,
            'user_id'       => $this->user->id,
            'activity_type' => 'export_generated',
        ]);
    }

    #[Test]
    public function excel_export_on_non_accepted_project_returns_403(): void
    {
        $draft = Project::factory()->for($this->user)->create(['status' => 'draft']);

        $this->actingAs($this->user)
             ->get("/projects/{$draft->id}/export/excel")
             ->assertStatus(403);
    }
}