<?php

namespace Tests\Feature;

use App\Models\OutdoorRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OutdoorRoutesPropTest extends TestCase
{
    use RefreshDatabase;

    private function makeRoute(User $user, string $name): OutdoorRoute
    {
        return OutdoorRoute::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'geometry'   => [[-6.2, 106.8], [-6.2001, 106.8]],
            'distance_m' => '11.120',
        ]);
    }

    public function test_outdoor_page_exposes_only_own_routes_newest_first_and_max_vertices(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->makeRoute($user, 'Rute Pertama');
        $this->makeRoute($user, 'Rute Kedua');
        $this->makeRoute($other, 'Rute Orang Lain');

        $this->actingAs($user)
            ->get(route('outdoor.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Outdoor/Index')
                ->has('routes', 2)
                ->where('routes.0.name', 'Rute Kedua')
                ->where('routes.1.name', 'Rute Pertama')
                ->where('routeMaxVertices', config('geolevel.outdoor.route_max_vertices'))
                ->etc()
            );
    }
}
