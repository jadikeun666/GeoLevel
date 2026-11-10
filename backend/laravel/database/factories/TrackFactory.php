<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'project_id' => null,
            'name'       => 'Track ' . $this->faker->unique()->numberBetween(1, 99999),
            'source'     => 'gpx',
            'status'     => 'finished',
        ];
    }
}
