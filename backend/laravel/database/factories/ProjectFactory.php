<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'             => User::factory(),
            'name'                => fake()->words(3, true),
            'location'            => fake()->city(),
            'survey_date'         => fake()->date(),
            'benchmark_name'      => 'BM-A',
            'benchmark_elevation' => '100.0000',
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
        ];
    }
}
