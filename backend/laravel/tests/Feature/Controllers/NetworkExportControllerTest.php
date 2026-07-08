<?php

namespace Tests\Feature\Controllers;

use App\Models\NetworkLeg;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test — NetworkLegController::exportPdf / exportExcel
 *
 * Controller sekarang stream file langsung (synchronous), bukan queue Job.
 * - Sukses: HTTP 200 + Content-Disposition attachment
 * - Guard fail: HTTP 403
 */
class NetworkExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private function acceptedProjectWithLegs(User $user): Project
    {
        $project = Project::factory()->create([
            'user_id'             => $user->id,
            'status'             => 'accepted',
            'benchmark_name'      => 'BM-A',
            'benchmark_elevation' => '100.0000',
        ]);

        NetworkLeg::factory()->create([
            'project_id'       => $project->id,
            'from_point'       => 'BM-A',
            'to_point'         => 'TP-1',
            'observed_delta_h' => '1.000000',
            'distance_m'       => '100.000',
        ]);

        return $project;
    }

    // ── PDF export ────────────────────────────────────────────────

    public function test_pdf_export_returns_200_with_pdf_content_type(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_pdf_export_has_attachment_disposition(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/pdf");

        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function test_pdf_export_returns_403_when_project_not_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['draft', 'calculated', 'rejected'] as $status) {
            $project = Project::factory()->create([
                'user_id' => $user->id,
                'status'  => $status,
            ]);
            NetworkLeg::factory()->create([
                'project_id'       => $project->id,
                'from_point'       => 'BM-A',
                'to_point'         => 'TP-1',
                'observed_delta_h' => '1.000000',
                'distance_m'       => '100.000',
            ]);

            $response = $this->actingAs($user)
                ->get("/projects/{$project->id}/network-legs/export/pdf");

            $response->assertStatus(403, "Expected 403 for status={$status}");
        }
    }

    public function test_pdf_export_returns_403_when_no_network_legs(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'status'  => 'accepted',
        ]);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/pdf");

        $response->assertStatus(403);
    }

    public function test_pdf_export_unauthenticated_returns_401(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->get("/projects/{$project->id}/network-legs/export/pdf");

        $response->assertStatus(401);
    }

    public function test_pdf_export_other_users_project_returns_403(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($owner);

        $response = $this->actingAs($other)
            ->get("/projects/{$project->id}/network-legs/export/pdf");

        $response->assertStatus(403);
    }

    // ── Excel export ──────────────────────────────────────────────

    public function test_excel_export_returns_200_with_xlsx_content_type(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/excel");

        $response->assertStatus(200);
        // xlsx MIME type
        $contentType = $response->headers->get('Content-Type') ?? '';
        $this->assertTrue(
            str_contains($contentType, 'spreadsheet') || str_contains($contentType, 'excel') || str_contains($contentType, 'octet-stream'),
            "Expected xlsx content type, got: {$contentType}"
        );
    }

    public function test_excel_export_has_attachment_disposition(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/excel");

        $this->assertStringContainsString(
            'attachment',
            $response->headers->get('Content-Disposition') ?? ''
        );
    }

    public function test_excel_export_returns_403_when_project_not_accepted(): void
    {
        $user = User::factory()->create();

        foreach (['draft', 'calculated', 'rejected'] as $status) {
            $project = Project::factory()->create([
                'user_id' => $user->id,
                'status'  => $status,
            ]);
            NetworkLeg::factory()->create([
                'project_id'       => $project->id,
                'from_point'       => 'BM-A',
                'to_point'         => 'TP-1',
                'observed_delta_h' => '1.000000',
                'distance_m'       => '100.000',
            ]);

            $response = $this->actingAs($user)
                ->get("/projects/{$project->id}/network-legs/export/excel");

            $response->assertStatus(403, "Expected 403 for status={$status}");
        }
    }

    public function test_excel_export_returns_403_when_no_network_legs(): void
    {
        $user    = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'status'  => 'accepted',
        ]);

        $response = $this->actingAs($user)
            ->get("/projects/{$project->id}/network-legs/export/excel");

        $response->assertStatus(403);
    }

    public function test_excel_export_unauthenticated_returns_401(): void
    {
        $user    = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($user);

        $response = $this->get("/projects/{$project->id}/network-legs/export/excel");

        $response->assertStatus(401);
    }

    public function test_excel_export_other_users_project_returns_403(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $project = $this->acceptedProjectWithLegs($owner);

        $response = $this->actingAs($other)
            ->get("/projects/{$project->id}/network-legs/export/excel");

        $response->assertStatus(403);
    }
}
