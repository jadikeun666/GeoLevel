<?php

namespace Tests\Feature;

use App\Models\OutdoorRoute;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OutdoorRouteTest extends TestCase
{
    use RefreshDatabase;

    // Two vertices 1 degree of latitude apart: distance = R * pi/180, R = 6371008.8 (geolevel.earth_radius_m).
    private const ONE_DEGREE_M = 111195.080;

    private function body(array $overrides = []): array
    {
        return $overrides + ['name' => 'Jalur A', 'geometry' => [[0, 0], [1, 0]]];
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson(route('outdoor.routes.index'))->assertUnauthorized();
    }

    public function test_store_computes_distance_on_the_server(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->postJson(route('outdoor.routes.store'), $this->body(['distance_m' => 1]))
            ->assertCreated();

        $this->assertEqualsWithDelta(self::ONE_DEGREE_M, (float) $response->json('data.distance_m'), 0.01);
        $this->assertSame([[0, 0], [1, 0]], $response->json('data.geometry'));
    }

    public function test_index_returns_only_own_routes(): void
    {
        $user = User::factory()->create();
        OutdoorRoute::factory()->create(['user_id' => $user->id]);
        OutdoorRoute::factory()->create();

        $this->actingAs($user)->getJson(route('outdoor.routes.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_update_recomputes_distance(): void
    {
        $user  = User::factory()->create();
        $route = OutdoorRoute::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->putJson(route('outdoor.routes.update', $route), $this->body(['name' => 'Baru']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Baru');

        $this->assertEqualsWithDelta(self::ONE_DEGREE_M, (float) $response->json('data.distance_m'), 0.01);
    }

    public function test_destroy_removes_the_route(): void
    {
        $user  = User::factory()->create();
        $route = OutdoorRoute::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('outdoor.routes.destroy', $route))->assertOk();

        $this->assertModelMissing($route);
    }

    public function test_other_users_cannot_update_or_delete(): void
    {
        $route = OutdoorRoute::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->putJson(route('outdoor.routes.update', $route), $this->body())->assertForbidden();
        $this->actingAs($other)->deleteJson(route('outdoor.routes.destroy', $route))->assertForbidden();
    }

    public static function invalidBodies(): array
    {
        return [
            'single vertex'      => [['geometry' => [[0, 0]]], 'geometry'],
            'too many vertices'  => [['geometry' => array_fill(0, 501, [0, 0])], 'geometry'],
            'latitude too large' => [['geometry' => [[95, 0], [1, 0]]], 'geometry.0.0'],
        ];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_geometry_is_rejected(array $overrides, string $errorKey): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('outdoor.routes.store'), $this->body($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    }

    public function test_project_of_another_user_is_rejected(): void
    {
        $foreign = Project::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('outdoor.routes.store'), $this->body(['project_id' => $foreign->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('project_id');
    }
}
