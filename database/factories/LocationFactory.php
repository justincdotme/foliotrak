<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'South window', 'Kitchen sill', 'Office desk', 'Bathroom shelf',
                'Living room', 'Bedroom window', 'Dining table', 'Patio',
                'Sunroom', 'Hallway', 'Front porch', 'Back porch',
            ]),
        ];
    }
}
