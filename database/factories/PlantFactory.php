<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlantStatus;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plant>
 */
class PlantFactory extends Factory
{
    protected $model = Plant::class;

    public function definition(): array
    {
        return [
            'common_name' => fake()->randomElement(['Pothos', 'Monstera', 'Snake plant', 'Fiddle leaf fig', 'ZZ plant']),
            'scientific_name' => fake()->optional()->randomElement(['Epipremnum aureum', 'Monstera deliciosa', 'Dracaena trifasciata']),
            'gbif_key' => null,
            'location_id' => null,
            'acquired_on' => fake()->optional()->dateTimeBetween('-2 years')?->format('Y-m-d'),
            'status' => PlantStatus::Active,
            'notes' => fake()->optional()->sentence(),
            'watering_interval_days_override' => null,
            'watering_schedule_start_date' => null,
            'fertilizing_interval_days_override' => null,
            'cover_photo_id' => null,
        ];
    }
}
