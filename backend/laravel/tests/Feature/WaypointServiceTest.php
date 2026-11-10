<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Waypoint;
use App\Services\WaypointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WaypointServiceTest extends TestCase
{
    use RefreshDatabase;

    private WaypointService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WaypointService();
    }

    #[Test]
    public function create_assigns_owner_and_defaults(): void
    {
        $user = User::factory()->create();

        $waypoint = $this->service->create($user, [
            'name' => 'Pos 1',
            'lat'  => '-6.20000000',
            'lng'  => '106.80000000',
        ]);

        $this->assertSame($user->id, $waypoint->user_id);
        $this->assertSame('generic', $waypoint->category);
        $this->assertSame('manual', $waypoint->source);
        $this->assertNull($waypoint->project_id);
    }

    #[Test]
    public function create_ignores_user_id_from_payload(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $waypoint = $this->service->create($user, [
            'user_id' => $other->id,
            'name'    => 'Pos 2',
            'lat'     => '-6.2',
            'lng'     => '106.8',
        ]);

        $this->assertSame($user->id, $waypoint->user_id);
    }

    #[Test]
    public function create_enforces_per_user_limit_only_for_that_user(): void
    {
        config(['geolevel.outdoor.waypoint_max_per_user' => 2]);

        $user  = User::factory()->create();
        $other = User::factory()->create();
        Waypoint::factory()->count(2)->create(['user_id' => $user->id]);

        $this->assertNotNull($this->service->create($other, ['name' => 'Lain', 'lat' => '-6', 'lng' => '106']));

        $this->expectException(ValidationException::class);

        $this->service->create($user, ['name' => 'Ketiga', 'lat' => '-6', 'lng' => '106']);
    }

    #[Test]
    public function update_changes_fields_but_not_owner_or_source(): void
    {
        $user     = User::factory()->create();
        $other    = User::factory()->create();
        $waypoint = Waypoint::factory()->create(['user_id' => $user->id, 'source' => 'gpx']);

        $updated = $this->service->update($waypoint, [
            'user_id' => $other->id,
            'source'  => 'manual',
            'name'    => 'Nama Baru',
        ]);

        $this->assertSame('Nama Baru', $updated->name);
        $this->assertSame($user->id, $updated->user_id);
        $this->assertSame('gpx', $updated->source);
    }

    #[Test]
    public function list_for_returns_only_own_waypoints(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        Waypoint::factory()->count(2)->create(['user_id' => $user->id]);
        Waypoint::factory()->create(['user_id' => $other->id]);

        $this->assertCount(2, $this->service->listFor($user));
    }

    #[Test]
    public function delete_removes_waypoint(): void
    {
        $waypoint = Waypoint::factory()->create();

        $this->service->delete($waypoint);

        $this->assertDatabaseMissing('waypoints', ['id' => $waypoint->id]);
    }
}
