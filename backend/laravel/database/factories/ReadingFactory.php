<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'   => Project::factory(),
            'sequence_no'  => 1,
            'point_name'   => 'BM-A',
            'reading_type' => 'BS',
            'ba'           => '1.5230',
            'bt'           => '1.2100',
            'bb'           => '0.8970',
        ];
    }
}
