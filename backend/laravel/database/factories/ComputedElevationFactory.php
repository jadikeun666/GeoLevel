<?php

namespace Database\Factories;

use App\Models\ComputedElevation;
use App\Models\Project;
use App\Models\Reading;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ComputedElevation> */
class ComputedElevationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),

            // reading_id resolved via closure so the Reading is created with
            // the same project_id that was resolved for this ComputedElevation,
            // and withoutEvents() prevents ReadingObserver from firing
            // RecalculateSurveyJob during factory setup.
            'reading_id' => function (array $attributes) {
                return Reading::withoutEvents(
                    fn () => Reading::factory()->create([
                        'project_id' => $attributes['project_id'],
                    ])->id
                );
            },

            'sequence_no'         => $this->faker->unique()->numberBetween(1, 999),
            'point_name'          => $this->faker->bothify('TP-##'),
            'hi'                  => null,
            'raw_elevation'       => number_format($this->faker->randomFloat(4, 90, 110), 4, '.', ''),
            'correction'          => '0.000000',
            'adjusted_elevation'  => number_format($this->faker->randomFloat(4, 90, 110), 4, '.', ''),
            'cumulative_distance' => number_format($this->faker->randomFloat(3, 0, 500), 3, '.', ''),
        ];
    }
}
