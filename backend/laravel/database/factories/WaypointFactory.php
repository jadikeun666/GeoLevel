<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaypointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'project_id'      => null,
            'survey_point_id' => null,
            'name'            => 'WP-' . $this->faker->unique()->numberBetween(1, 99999),
            'lat'             => $this->faker->latitude(-9, -6),
            'lng'             => $this->faker->longitude(105, 119),
            'elevation_ref'   => null,
            'gps_accuracy_m'  => null,
            'category'        => 'generic',
            'source'          => 'manual',
            'notes'           => null,
        ];
    }
}
