<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GrowthRate;
use App\Models\CareEvent;
use App\Models\Observation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    public function definition(): array
    {
        return [
            'care_event_id' => CareEvent::factory()->ofType('observation'),
            'overall_health' => fake()->numberBetween(1, 5),
            'health_note' => null,
            'light_level' => fake()->numberBetween(0, 10),
            'growth_rate' => fake()->randomElement(GrowthRate::cases()),
            'growth_note' => null,
            'leaf_size_mm' => null,
            'weight_grams' => fake()->numberBetween(100, 3000),
        ];
    }
}
