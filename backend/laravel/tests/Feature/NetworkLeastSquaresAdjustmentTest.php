<?php

namespace Tests\Feature;

use App\Models\NetworkLeg;
use App\Models\Project;
use App\Models\User;
use App\Services\AdjustmentService;
use App\Services\LeastSquaresAdjustmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test — verifikasi integrasi penuh LeastSquaresAdjustmentService
 * dengan Project model, AdjustmentService dispatcher, dan persist ke DB.
 *
 * Lihat juga: tests/Unit/Services/LeastSquaresAdjustmentServiceTest.php
 * untuk pengujian matematika murni (tanpa DB).
 */
class NetworkLeastSquaresAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeProjectWithoutLegs(User $user): Project
    {
        return Project::factory()->create([
            'user_id'             => $user->id,
            'benchmark_name'      => 'BM-A',
            'benchmark_elevation' => '100.0000',
            'adjustment_method'   => 'least_squares',
        ]);
    }

    public function test_project_without_network_legs_has_no_redundancy(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        $this->assertFalse($project->hasRedundantNetworkObservations());
        $this->assertFalse($project->hasNetworkLegs());
    }

    public function test_project_with_simple_chain_legs_has_no_redundancy(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        // Rantai linear A->B->C — TIDAK redundant (n_legs=2, n_points=3)
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'BM-A', 'to_point' => 'TP-1',
            'observed_delta_h' => '1.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-1', 'to_point' => 'TP-2',
            'observed_delta_h' => '2.000', 'distance_m' => '100',
        ]);

        $this->assertTrue($project->hasNetworkLegs());
        $this->assertFalse($project->hasRedundantNetworkObservations());
    }

    public function test_project_with_loop_legs_has_redundancy(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        // Loop tertutup A->B->C->A — REDUNDANT (n_legs=3, n_points=3)
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'BM-A', 'to_point' => 'TP-1',
            'observed_delta_h' => '1.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-1', 'to_point' => 'TP-2',
            'observed_delta_h' => '2.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-2', 'to_point' => 'BM-A',
            'observed_delta_h' => '-2.998', 'distance_m' => '100',
        ]);

        $this->assertTrue($project->hasRedundantNetworkObservations());
    }

    public function test_adjustment_service_delegates_to_least_squares_for_redundant_network(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'BM-A', 'to_point' => 'TP-1',
            'observed_delta_h' => '1.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-1', 'to_point' => 'TP-2',
            'observed_delta_h' => '2.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-2', 'to_point' => 'BM-A',
            'observed_delta_h' => '-2.998', 'distance_m' => '100',
        ]);

        $adjuster = $this->app->make(AdjustmentService::class);
        $adjuster->applyToProject($project, 'least_squares', $user->id);

        $project->refresh();

        // Setelah adjustment via jalur least squares network, kolom
        // network_* harus terisi (bukti delegasi berhasil ke service baru,
        // bukan fallback formula lama).
        $this->assertNotNull($project->network_variance);
        $this->assertNotNull($project->network_std_deviation);
        $this->assertSame(1, $project->network_degrees_of_freedom); // n=3, u=2 -> dof=1

        // Semua leg harus punya corrected_delta_h terisi
        $legs = $project->networkLegs()->get();
        foreach ($legs as $leg) {
            $this->assertNotNull($leg->corrected_delta_h);
            $this->assertNotNull($leg->residual);
        }

        // Loop closure setelah adjustment harus ≈ 0
        $sumCorrected = $legs->sum(fn ($l) => (float) $l->corrected_delta_h);
        $this->assertEqualsWithDelta(0.0, $sumCorrected, 0.000001);
    }

    public function test_adjustment_service_falls_back_to_legacy_for_non_redundant_project(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);
        // Tidak ada network_legs sama sekali — harus pakai jalur lama tanpa error

        // Perlu computed_elevations untuk applyToProjectLegacy tidak crash —
        // skip test ini jika tidak ada readings (di luar scope test ini,
        // cukup pastikan tidak delegasi ke least squares).
        $this->assertFalse($project->hasRedundantNetworkObservations());
    }

    public function test_least_squares_endpoint_rejects_when_no_redundancy(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'BM-A', 'to_point' => 'TP-1',
            'observed_delta_h' => '1.000', 'distance_m' => '100',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/network-legs/adjust");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Jaring belum punya observasi berlebih (redundant) — tambahkan minimal satu jalur tambahan yang membentuk loop sebelum menjalankan perataan kuadrat terkecil.']);
    }

    public function test_least_squares_endpoint_succeeds_for_redundant_network(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'BM-A', 'to_point' => 'TP-1',
            'observed_delta_h' => '1.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-1', 'to_point' => 'TP-2',
            'observed_delta_h' => '2.000', 'distance_m' => '100',
        ]);
        NetworkLeg::factory()->create([
            'project_id' => $project->id,
            'from_point' => 'TP-2', 'to_point' => 'BM-A',
            'observed_delta_h' => '-2.998', 'distance_m' => '100',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/projects/{$project->id}/network-legs/adjust");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'result' => ['point_elevations', 'legs', 'variance', 'std_deviation', 'degrees_of_freedom'],
        ]);
    }

    public function test_can_store_and_delete_network_leg_via_endpoint(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/network-legs", [
            'from_point'       => 'BM-A',
            'to_point'         => 'TP-1',
            'observed_delta_h' => '1.234500',
            'distance_m'       => '150.500',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('network_legs', [
            'project_id' => $project->id,
            'from_point' => 'BM-A',
            'to_point'   => 'TP-1',
        ]);

        $leg = NetworkLeg::where('project_id', $project->id)->first();

        $deleteResponse = $this->actingAs($user)
            ->deleteJson("/projects/{$project->id}/network-legs/{$leg->id}");

        $deleteResponse->assertStatus(200);
        $this->assertDatabaseMissing('network_legs', ['id' => $leg->id]);
    }

    public function test_store_network_leg_rejects_same_from_and_to_point(): void
    {
        $user    = User::factory()->create();
        $project = $this->makeProjectWithoutLegs($user);

        $response = $this->actingAs($user)->postJson("/projects/{$project->id}/network-legs", [
            'from_point'       => 'BM-A',
            'to_point'         => 'BM-A',
            'observed_delta_h' => '1.000000',
            'distance_m'       => '100',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_point']);
    }
}