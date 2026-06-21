<?php

namespace Database\Factories;

use App\Models\NetworkLeg;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkLegFactory extends Factory
{
    protected $model = NetworkLeg::class;

    public function definition(): array
    {
        return [
            'project_id'        => Project::factory(),
            'from_point'        => 'A',
            'to_point'          => 'B',
            'observed_delta_h'  => $this->faker->randomFloat(6, -10, 10),
            'distance_m'        => $this->faker->randomFloat(3, 50, 500),
            'corrected_delta_h' => null,
            'residual'          => null,
        ];
    }
}