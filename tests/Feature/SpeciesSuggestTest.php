<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeciesCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpeciesSuggestTest extends TestCase
{
    use RefreshDatabase;

    private function actAsHousehold(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_suggesting_requires_authentication(): void
    {
        $this->getJson('/api/species/suggest?q=monstera')->assertUnauthorized();
    }

    public function test_requires_a_query(): void
    {
        $this->actAsHousehold();
        $this->getJson('/api/species/suggest')
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('q');
    }

    public function test_suggests_species_and_writes_through_to_the_cache(): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/*' => Http::response([
                [
                    'key' => 2868125,
                    'scientificName' => 'Monstera deliciosa Liebm.',
                    'canonicalName' => 'Monstera deliciosa',
                    'rank' => 'SPECIES',
                    'family' => 'Araceae',
                ],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '2868125')
            ->assertJsonPath('data.0.scientific_name', 'Monstera deliciosa Liebm.')
            ->assertJsonPath('data.0.canonical_name', 'Monstera deliciosa')
            ->assertJsonPath('data.0.family', 'Araceae')
            ->assertJsonPath('data.0.rank', 'SPECIES')
            ->assertJsonPath('data.0.common_name', null);

        $this->assertDatabaseHas('species_cache', [
            'gbif_key' => '2868125',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'canonical_name' => 'Monstera deliciosa',
        ]);
    }

    public function test_serves_matching_prefixes_from_the_cache_when_gbif_is_unavailable(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '5414516',
            'scientific_name' => 'Ficus lyrata Warb.',
            'canonical_name' => 'Ficus lyrata',
        ]);
        // A non-matching row must stay out of the prefix-filtered fallback.
        SpeciesCache::factory()->create([
            'gbif_key' => '2873815',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'canonical_name' => 'Monstera deliciosa',
        ]);
        Http::fake(['api.gbif.org/*' => Http::response('upstream down', 503)]);

        $this->getJson('/api/species/suggest?q=Ficus')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.gbif_key', '5414516')
            ->assertJsonPath('data.0.scientific_name', 'Ficus lyrata Warb.');
    }

    #[DataProvider('outOfRangeLimits')]
    public function test_rejects_an_out_of_range_limit(int $limit): void
    {
        $this->actAsHousehold();

        $this->getJson("/api/species/suggest?q=ficus&limit={$limit}")
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('limit');
    }

    /**
     * @return array<string, array{int}>
     */
    public static function outOfRangeLimits(): array
    {
        return [
            'below minimum' => [0],
            'above maximum' => [21],
        ];
    }
}
