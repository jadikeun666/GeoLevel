<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class SurveyPointFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'     => Project::factory(),
            'point_name'     => 'BM-' . $this->faker->unique()->numberBetween(1, 9999),
            'lat'            => $this->faker->latitude(-9, -6),
            'lng'            => $this->faker->longitude(105, 119),
            'point_type'     => $this->faker->randomElement(['BM', 'TP', 'IS', 'CP']),
            'elevation_ref'  => null,
            'gps_accuracy_m' => null,
            'source'         => 'manual',
            'notes'          => null,
        ];
    }
}
