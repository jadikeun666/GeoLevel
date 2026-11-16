<?php

namespace Database\Factories;

use App\Models\OutdoorRoute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutdoorRoute>
 */
class OutdoorRouteFactory extends Factory
{
    protected $model = OutdoorRoute::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'project_id' => null,
            'name'       => fake()->words(3, true),
            'geometry'   => [[-6.9, 107.6], [-6.91, 107.61]],
            'distance_m' => null,
            'notes'      => null,
        ];
    }
}
