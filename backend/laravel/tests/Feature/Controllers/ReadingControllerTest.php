<?php

namespace Tests\Feature\Controllers;
use PHPUnit\Framework\Attributes\Test;

use App\Models\Project;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\RecalculateSurveyJob;
use Tests\TestCase;

/**
 * Feature tests for ReadingController CRUD.
 *
 * Verifies:
 *   - BT deviation validation (≤ 0.002 m) on create/update
 *   - RecalculateSurveyJob dispatched on every mutation (save / update / delete)
 *   - raw readings are never mutated
 *   - correct HTTP status codes
 *   - sequence_no ordering preserved in responses
 */
class ReadingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User    $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->user    = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create([
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'status'              => 'draft',
        ]);
    }

    // ---------------------------------------------------------------------------
    // POST /projects/{id}/readings  — create
    // ---------------------------------------------------------------------------

    #[Test]
    public function creating_a_reading_returns_201_and_persists_record(): void
    {
        $payload = $this->validBsPayload(sequence_no: 1, point_name: 'BM-A');

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", $payload)
             ->assertStatus(201);

        $this->assertDatabaseHas('readings', [
            'project_id'   => $this->project->id,
            'point_name'   => 'BM-A',
            'reading_type' => 'BS',
            'sequence_no'  => 1,
        ]);
    }

    #[Test]
    public function creating_a_reading_dispatches_recalculate_job(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", $this->validBsPayload(1, 'BM-A'));

        Queue::assertPushed(RecalculateSurveyJob::class);
    }

    #[Test]
    public function creating_a_reading_with_bt_deviation_above_limit_returns_422(): void
    {
        // BT_computed = (1.5230 + 0.8970) / 2 = 1.2100
        // BT_field    = 1.2130 → deviation = 0.003 > 0.002 → reject
        $payload = array_merge($this->validBsPayload(1, 'BM-A'), ['bt' => '1.2130']);

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", $payload)
             ->assertStatus(422)
             ->assertJsonValidationErrors(['bt']);
    }

    #[Test]
    public function creating_a_reading_with_bt_deviation_at_limit_returns_201(): void
    {
        // BT_computed = 1.2100; BT_field = 1.2120 → deviation = 0.002 (at limit — valid)
        $payload = array_merge($this->validBsPayload(1, 'BM-A'), ['bt' => '1.2120']);

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", $payload)
             ->assertStatus(201);
    }

    #[Test]
    public function creating_a_reading_does_not_dispatch_job_when_validation_fails(): void
    {
        $payload = array_merge($this->validBsPayload(1, 'BM-A'), ['bt' => '1.2130']);

        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", $payload);

        Queue::assertNotPushed(RecalculateSurveyJob::class);
    }

    #[Test]
    public function creating_a_reading_requires_all_mandatory_fields(): void
    {
        $this->actingAs($this->user)
             ->postJson("/projects/{$this->project->id}/readings", [])
             ->assertStatus(422)
             ->assertJsonValidationErrors(['sequence_no', 'point_name', 'reading_type', 'ba', 'bt', 'bb']);
    }

    // ---------------------------------------------------------------------------
    // PUT /projects/{id}/readings/{rid}  — update
    // ---------------------------------------------------------------------------

    #[Test]
    public function updating_a_reading_returns_200_and_persists_changes(): void
    {
        $reading = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->putJson("/projects/{$this->project->id}/readings/{$reading->id}", [
                 'point_name' => 'BM-START',
             ] + $this->validBsPayload(1, 'BM-START'))
             ->assertStatus(200);

        $this->assertDatabaseHas('readings', ['id' => $reading->id, 'point_name' => 'BM-START']);
    }

    #[Test]
    public function updating_a_reading_dispatches_recalculate_job(): void
    {
        $reading = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->putJson("/projects/{$this->project->id}/readings/{$reading->id}", $this->validBsPayload(1, 'BM-A'));

        Queue::assertPushed(RecalculateSurveyJob::class);
    }

    #[Test]
    public function updating_a_reading_with_invalid_bt_deviation_returns_422(): void
    {
        $reading = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->putJson("/projects/{$this->project->id}/readings/{$reading->id}", [
                 'bt' => '1.2130',
             ] + $this->validBsPayload(1, 'BM-A'))
             ->assertStatus(422)
             ->assertJsonValidationErrors(['bt']);
    }

    #[Test]
    public function update_does_not_modify_other_project_readings(): void
    {
        $readingA = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));
        $readingB = Reading::factory()->for($this->project)->create($this->validFsPayload(2, 'TP-1'));

        $originalB = $readingB->fresh()->toArray();

        $this->actingAs($this->user)
             ->putJson("/projects/{$this->project->id}/readings/{$readingA->id}", $this->validBsPayload(1, 'BM-A'));

        $this->assertEquals($originalB, $readingB->fresh()->toArray());
    }

    // ---------------------------------------------------------------------------
    // DELETE /projects/{id}/readings/{rid}
    // ---------------------------------------------------------------------------

    #[Test]
    public function deleting_a_reading_returns_204_and_soft_deletes(): void
    {
        $reading = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->deleteJson("/projects/{$this->project->id}/readings/{$reading->id}")
             ->assertStatus(204);

        // Hard-delete is forbidden per engineering rules — record must still exist
        $this->assertDatabaseHas('readings', ['id' => $reading->id]);

        // But it must be soft-deleted (deleted_at set)
        $this->assertSoftDeleted('readings', ['id' => $reading->id]);
    }

    #[Test]
    public function deleting_a_reading_dispatches_recalculate_job(): void
    {
        $reading = Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->deleteJson("/projects/{$this->project->id}/readings/{$reading->id}");

        Queue::assertPushed(RecalculateSurveyJob::class);
    }

    #[Test]
    public function cannot_delete_reading_belonging_to_another_project(): void
    {
        $otherProject = Project::factory()->for($this->user)->create();
        $reading      = Reading::factory()->for($otherProject)->create($this->validBsPayload(1, 'BM-A'));

        $this->actingAs($this->user)
             ->deleteJson("/projects/{$this->project->id}/readings/{$reading->id}")
             ->assertStatus(404);
    }

    // ---------------------------------------------------------------------------
    // GET — list (sequence_no ordering)
    // ---------------------------------------------------------------------------

    #[Test]
    public function readings_returned_in_sequence_no_asc_order(): void
    {
        Reading::factory()->for($this->project)->create($this->validFsPayload(3, 'BM-B'));
        Reading::factory()->for($this->project)->create($this->validFsPayload(2, 'TP-1'));
        Reading::factory()->for($this->project)->create($this->validBsPayload(1, 'BM-A'));

        $response = $this->actingAs($this->user)
                         ->getJson("/projects/{$this->project->id}")
                         ->assertStatus(200);

        $sequences = collect($response->json('readings'))->pluck('sequence_no')->all();
        $this->assertSame([1, 2, 3], $sequences);
    }

    // ---------------------------------------------------------------------------
    // Authorisation
    // ---------------------------------------------------------------------------

    #[Test]
    public function unauthenticated_user_cannot_create_reading(): void
    {
        $this->postJson("/projects/{$this->project->id}/readings", $this->validBsPayload(1, 'BM-A'))
             ->assertStatus(401);
    }

    #[Test]
    public function user_cannot_access_readings_of_another_users_project(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
             ->getJson("/projects/{$this->project->id}")
             ->assertStatus(403);
    }

    // ---------------------------------------------------------------------------
    // Helpers — valid payloads from canonical dataset
    // ---------------------------------------------------------------------------

    private function validBsPayload(int $sequence_no, string $point_name): array
    {
        return [
            'sequence_no'  => $sequence_no,
            'point_name'   => $point_name,
            'reading_type' => 'BS',
            'ba'           => '1.5230',
            'bt'           => '1.2100', // bt_computed = (1.5230+0.8970)/2 = 1.2100 → deviation = 0
            'bb'           => '0.8970',
        ];
    }

    private function validFsPayload(int $sequence_no, string $point_name): array
    {
        return [
            'sequence_no'  => $sequence_no,
            'point_name'   => $point_name,
            'reading_type' => 'FS',
            'ba'           => '1.4870',
            'bt'           => '1.1750', // bt_computed = (1.4870+0.8630)/2 = 1.1750 → deviation = 0
            'bb'           => '0.8630',
        ];
    }
}