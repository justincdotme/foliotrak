<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SpeciesCache;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpeciesCache>
 */
class SpeciesCacheFactory extends Factory
{
    /** @var class-string<SpeciesCache> */
    protected $model = SpeciesCache::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gbif_key'        => (string) fake()->unique()->numberBetween(1000, 9999999),
            'scientific_name' => fake()->words(2, true),
            'canonical_name'  => fake()->optional()->words(2, true),
            'common_name'     => null,
            'common_names'    => null,
            'rank'            => 'SPECIES',
            'family'          => fake()->word(),
            'payload'         => null,
        ];
    }
}
