<?php

namespace Database\Factories;

use App\Models\CrossSection;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class CrossSectionFactory extends Factory
{
    protected $model = CrossSection::class;

    public function definition(): array
    {
        return [
            'project_id'       => Project::factory(),
            'station_name'     => 'STA ' . $this->faker->numerify('0+###'),
            'station_distance' => $this->faker->randomFloat(3, 0, 500),
            'offsets'          => [
                ['side' => 'L', 'distance' => 3.0,  'elevation' => $this->faker->randomFloat(3, 98, 102)],
                ['side' => 'C', 'distance' => 0.0,  'elevation' => $this->faker->randomFloat(3, 98, 102)],
                ['side' => 'R', 'distance' => 3.0,  'elevation' => $this->faker->randomFloat(3, 98, 102)],
            ],
        ];
    }
}
