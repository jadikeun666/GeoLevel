<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'track_id'    => Track::factory(),
            'seq'         => 1,
            'lat'         => $this->faker->latitude(-9, -6),
            'lng'         => $this->faker->longitude(105, 119),
            'recorded_at' => now(),
        ];
    }
}
