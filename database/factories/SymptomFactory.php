<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SymptomCategory;
use App\Models\Symptom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Symptom>
 */
class SymptomFactory extends Factory
{
    protected $model = Symptom::class;

    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'category' => fake()->randomElement(SymptomCategory::cases()),
            'key' => Symptom::slugFor($label),
            'label' => ucfirst($label),
            'sort_order' => fake()->numberBetween(1, 20),
            'is_custom' => false,
        ];
    }
}
