<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;

use App\Models\Project;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Queue::fake() di setUp() mencegah RecalculateSurveyJob berjalan
        // synchronously saat test. Tanpa ini:
        //   1. ReadingObserver dispatch RecalculateSurveyJob secara sync
        //   2. LevelingCalculationService berjalan dengan data tidak lengkap
        //   3. Exception menyebabkan rollback transaksi seluruhnya
        //   4. Reading tidak tersimpan → user_can_add_valid_reading gagal
        //   5. user_can_delete_reading_on_draft_project gagal karena
        //      Reading::factory()->create() juga rollback
        //
        // Test yang perlu verifikasi job dispatch menggunakan
        // Queue::assertPushed() di dalam method test masing-masing —
        // ini tetap bekerja karena Queue::fake() mencatat semua dispatch.
        Queue::fake();

        $this->user    = User::factory()->create();
        $this->project = Project::factory()->create([
            'user_id'             => $this->user->id,
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
        ]);
    }

    // ── Auth guard ────────────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_access_projects(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function another_user_cannot_view_project(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other)
            ->get(route('projects.show', $this->project->id))
            ->assertForbidden();
    }

    // ── Project CRUD ──────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_see_projects_index(): void
    {
        // Memerlukan resources/views/app.blade.php untuk Inertia render.
        $this->actingAs($this->user)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Projects/Index'));
    }

    #[Test]
    public function user_can_create_project(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.store'), [
                'name'                => 'Proyek Test',
                'location'            => 'Lampung',
                'survey_date'         => '2025-06-01',
                'benchmark_name'      => 'BM-TEST',
                'benchmark_elevation' => '50.0000',
                'tolerance_class'     => 'LA',
                'adjustment_method'   => 'equal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name'    => 'Proyek Test',
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function project_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.store'), [])
            ->assertSessionHasErrors(['name', 'location', 'survey_date', 'benchmark_name', 'benchmark_elevation']);
    }

    #[Test]
    public function user_can_view_own_project(): void
    {
        // Memerlukan resources/views/app.blade.php untuk Inertia render.
        $this->actingAs($this->user)
            ->get(route('projects.show', $this->project->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Projects/Show'));
    }

    #[Test]
    public function user_can_delete_own_project(): void
    {
        $this->actingAs($this->user)
            ->delete(route('projects.destroy', $this->project->id))
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseMissing('projects', ['id' => $this->project->id]);
    }

    // ── Reading CRUD ──────────────────────────────────────────────────────

    #[Test]
    public function user_can_add_valid_reading(): void
    {
        // Data dari formulas.md worked example baris pertama.
        // BT_computed = (1.5230 + 0.8970) / 2 = 1.2100
        // BT_deviation = |1.2100 - 1.2100| = 0.0000 ≤ 0.002 ✓
        $this->actingAs($this->user)
            ->post(route('readings.store', $this->project->id), [
                'point_name'   => 'BM-A',
                'reading_type' => 'BS',
                'ba'           => '1.5230',
                'bt'           => '1.2100',
                'bb'           => '0.8970',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('readings', [
            'project_id'   => $this->project->id,
            'point_name'   => 'BM-A',
            'reading_type' => 'BS',
        ]);
    }

    #[Test]
    public function reading_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->post(route('readings.store', $this->project->id), [])
            ->assertSessionHasErrors(['point_name', 'reading_type', 'ba', 'bt', 'bb']);
    }

    #[Test]
    public function reading_store_rejects_invalid_reading_type(): void
    {
        $this->actingAs($this->user)
            ->post(route('readings.store', $this->project->id), [
                'point_name'   => 'X',
                'reading_type' => 'XX',
                'ba'           => '1.0',
                'bt'           => '1.0',
                'bb'           => '1.0',
            ])
            ->assertSessionHasErrors(['reading_type']);
    }

    #[Test]
    public function user_can_delete_reading_on_draft_project(): void
    {
        // Buat satu reading — ini adalah satu-satunya reading di project,
        // sehingga sequence_no-nya adalah yang tertinggi dan delete diizinkan.
        // Queue::fake() dari setUp() mencegah observer crash saat factory create.
        $reading = Reading::factory()->create([
            'project_id'   => $this->project->id,
            'sequence_no'  => 1,
            'reading_type' => 'BS',
            'point_name'   => 'BM-A',
            'ba'           => '1.5230',
            'bt'           => '1.2100',
            'bb'           => '0.8970',
        ]);

        $this->actingAs($this->user)
            ->delete(route('readings.destroy', [$this->project->id, $reading->id]))
            ->assertRedirect();

        // Engineering Rule #10: soft delete — row tetap ada, deleted_at di-set
        $this->assertSoftDeleted('readings', ['id' => $reading->id]);
    }

    // ── Calculate / Adjust ────────────────────────────────────────────────

    #[Test]
    public function calculate_endpoint_dispatches_job_and_redirects(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.calculate', $this->project->id))
            ->assertRedirect();

        // Queue::fake() dari setUp() masih aktif — assertPushed tetap bekerja
        Queue::assertPushed(\App\Jobs\RecalculateSurveyJob::class);
    }

    #[Test]
    public function adjust_endpoint_requires_valid_method(): void
    {
        $this->actingAs($this->user)
            ->post(route('projects.adjust', $this->project->id), ['method' => 'magic'])
            ->assertSessionHasErrors(['method']);
    }

    #[Test]
    public function adjust_endpoint_accepts_equal_and_bowditch(): void
    {
        foreach (['equal', 'bowditch'] as $method) {
            $this->actingAs($this->user)
                ->post(route('projects.adjust', $this->project->id), ['method' => $method])
                ->assertRedirect();
        }
    }

    // ── Export guard ──────────────────────────────────────────────────────

    #[Test]
    public function export_pdf_returns_error_when_project_not_accepted(): void
    {
        // project.status = 'draft' dari setUp()
        $this->actingAs($this->user)
            ->get(route('export.pdf', $this->project->id))
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function export_pdf_queues_job_when_project_accepted(): void
    {
        $this->project->update(['status' => 'accepted']);

        $this->actingAs($this->user)
            ->get(route('export.pdf', $this->project->id))
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\GeneratePdfExportJob::class);
    }

    #[Test]
    public function export_excel_queues_job_when_project_accepted(): void
    {
        $this->project->update(['status' => 'accepted']);

        $this->actingAs($this->user)
            ->get(route('export.excel', $this->project->id))
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\GenerateExcelExportJob::class);
    }

    #[Test]
    public function export_csv_queues_job_when_project_accepted(): void
    {
        $this->project->update(['status' => 'accepted']);

        $this->actingAs($this->user)
            ->get(route('export.csv', $this->project->id))
            ->assertJson(['success' => true]);

        Queue::assertPushed(\App\Jobs\GenerateCsvExportJob::class);
    }

    // ── Chart endpoints ───────────────────────────────────────────────────

    #[Test]
    public function long_section_chart_returns_json_array(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('chart.longsection', $this->project->id))
            ->assertOk()
            ->assertJsonStructure(['data', 'title']);
    }

    #[Test]
    public function cross_section_chart_returns_json(): void
    {
        $this->actingAs($this->user)
            ->getJson(route('chart.crosssection', $this->project->id))
            ->assertOk()
            ->assertJsonStructure(['data', 'stations']);
    }
}