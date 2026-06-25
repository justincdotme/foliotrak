<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Photo;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    protected $model = Photo::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'care_event_id' => null,
            'disk' => 'photos',
            'path' => fake()->uuid().'.jpg',
            'original_filename' => fake()->word().'.jpg',
            'taken_on' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'caption' => fake()->optional()->sentence(),
        ];
    }
}
