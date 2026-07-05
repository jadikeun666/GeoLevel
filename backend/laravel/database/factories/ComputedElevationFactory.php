<?php

namespace Database\Factories;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\Reading;
use App\Observers\ReadingObserver;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ComputedElevation> */
class ComputedElevationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id'          => Project::factory(),
            'reading_id'          => $this->makeReadingWithoutObserver(),
            'sequence_no'         => $this->faker->numberBetween(1, 100),
            'point_name'          => $this->faker->bothify('TP-##'),
            'hi'                  => null,
            'raw_elevation'       => number_format($this->faker->randomFloat(4, 90, 110), 4, '.', ''),
            'correction'          => '0.000000',
            'adjusted_elevation'  => number_format($this->faker->randomFloat(4, 90, 110), 4, '.', ''),
            'cumulative_distance' => number_format($this->faker->randomFloat(3, 0, 500), 3, '.', ''),
        ];
    }

    private function makeReadingWithoutObserver(): int
    {
        Reading::flushEventListeners();
        $reading = Reading::factory()->create();
        Reading::observe(ReadingObserver::class);
        return $reading->id;
    }
}
