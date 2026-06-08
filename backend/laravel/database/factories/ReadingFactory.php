<?php
// ─── database/factories/ReadingFactory.php ────────────────────────────────

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadingFactory extends Factory
{
    public function definition(): array
    {
        $ba = round($this->faker->randomFloat(4, 0.5, 2.5), 4);
        $bb = round($ba - $this->faker->randomFloat(4, 0.3, 0.8), 4);
        $bt = round(($ba + $bb) / 2, 4);

        return [
            'project_id'   => Project::factory(),
            'sequence_no'  => $this->faker->unique()->numberBetween(1, 999),
            'point_name'   => 'TP-' . $this->faker->numberBetween(1, 99),
            'reading_type' => $this->faker->randomElement(['BS', 'IS', 'FS']),
            'ba'           => number_format($ba, 4, '.', ''),
            'bt'           => number_format($bt, 4, '.', ''),
            'bb'           => number_format($bb, 4, '.', ''),
            'distance_m'   => null,
            'notes'        => null,
        ];
    }

    public function bs(): static { return $this->state(['reading_type' => 'BS']); }
    public function fs(): static { return $this->state(['reading_type' => 'FS']); }
    public function is_type(): static { return $this->state(['reading_type' => 'IS']); }
}