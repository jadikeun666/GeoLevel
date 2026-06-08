<?php
// ─── database/factories/ProjectFactory.php ────────────────────────────────

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'             => User::factory(),
            'name'                => $this->faker->sentence(3),
            'location'            => $this->faker->city(),
            'survey_date'         => $this->faker->date(),
            'benchmark_name'      => 'BM-' . strtoupper($this->faker->lexify('??')),
            'benchmark_elevation' => number_format($this->faker->randomFloat(4, 50, 200), 4, '.', ''),
            'tolerance_class'     => 'LA',
            'adjustment_method'   => 'equal',
            'status'              => 'draft',
            'closure_error'       => null,
            'allowed_tolerance'   => null,
            'total_distance_km'   => null,
            'metadata'            => [],
        ];
    }

    public function accepted(): static { return $this->state(['status' => 'accepted']); }
    public function rejected(): static { return $this->state(['status' => 'rejected']); }
    public function calculated(): static { return $this->state(['status' => 'calculated']); }
}