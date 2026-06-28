<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SpeciesCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpeciesSuggestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the live GBIF API (ADR-0012). Any un-faked outbound
        // request throws here rather than silently hitting the network.
        Http::preventStrayRequests();
    }

    private function actAsHousehold(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function monsteraMatch(): array
    {
        return [
            'usageKey' => 2868241,
            'scientificName' => 'Monstera deliciosa Liebm.',
            'canonicalName' => 'Monstera deliciosa',
            'rank' => 'SPECIES',
            'status' => 'ACCEPTED',
            'confidence' => 95,
            'matchType' => 'FUZZY',
            'family' => 'Araceae',
        ];
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function fakeGbifMatch(array $match): void
    {
        Http::fake(['api.gbif.org/*' => Http::response($match)]);
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

    #[DataProvider('tooShortQueries')]
    public function test_rejects_queries_shorter_than_three_characters(string $query): void
    {
        $this->actAsHousehold();
        $this->getJson('/api/species/suggest?q='.$query)
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('q');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tooShortQueries(): array
    {
        return [
            'one char' => ['a'],
            'two chars' => ['mo'],
        ];
    }

    public function test_serves_a_local_hit_without_calling_gbif(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'canonical_name' => 'Monstera deliciosa',
            'cached_at' => now(),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '2868241')
            ->assertJsonPath('data.0.scientific_name', 'Monstera deliciosa Liebm.');

        Http::assertNothingSent();
    }

    public function test_misses_locally_then_fetches_from_gbif_and_backfills(): void
    {
        $this->actAsHousehold();
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=Monstera deliciosa')
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '2868241')
            ->assertJsonPath('data.0.canonical_name', 'Monstera deliciosa')
            ->assertJsonPath('data.0.family', 'Araceae');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/species/match')
            && str_contains($request->url(), 'name=')
            && str_contains($request->url(), 'strict=false')
            && str_contains($request->url(), 'verbose=true'));

        // Backfilled with a freshness stamp so it can later go stale.
        $this->assertDatabaseHas('species_cache', ['gbif_key' => '2868241']);
        $this->assertNotNull(SpeciesCache::query()->where('gbif_key', '2868241')->value('cached_at'));
    }

    public function test_corrects_a_typo_through_gbif_fuzzy_match(): void
    {
        $this->actAsHousehold();
        $this->fakeGbifMatch($this->monsteraMatch());

        // The misspelling has no local match; GBIF's fuzzy matcher corrects it.
        $this->getJson('/api/species/suggest?q=monstera delicosa')
            ->assertOk()
            ->assertJsonPath('data.0.scientific_name', 'Monstera deliciosa Liebm.');
    }

    public function test_does_not_cache_empty_results(): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'matchType' => 'NONE',
                'confidence' => 0,
                'synonym' => false,
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'count' => 0,
                'results' => [],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=notaplant')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/species/suggest?q=notaplant')->assertOk()->assertJsonCount(0, 'data');

        // Each request hits match + search = 4 total.
        Http::assertSentCount(4);
        $this->assertSame(0, SpeciesCache::query()->count());
    }

    public function test_low_confidence_match_is_treated_as_no_match(): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'usageKey' => 99,
                'scientificName' => 'Dubious match',
                'rank' => 'SPECIES',
                'confidence' => 50,
                'matchType' => 'FUZZY',
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'count' => 0,
                'results' => [],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=qwerty plant')->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, SpeciesCache::query()->count());
    }

    #[DataProvider('gbifOutages')]
    public function test_returns_503_when_gbif_is_unavailable(string $kind): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/*' => $kind === 'timeout'
                ? Http::failedConnection()
                : Http::response('upstream error', (int) $kind),
        ]);

        $this->getJson('/api/species/suggest?q=monstera deliciosa')
            ->assertStatus(503)
            ->assertJsonPath('code', 'search_degraded');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function gbifOutages(): array
    {
        return [
            'server error' => ['500'],
            'rate limited' => ['429'],
            'timeout' => ['timeout'],
        ];
    }

    public function test_a_stale_hit_is_refreshed_from_gbif(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'family' => 'Stale family',
            'cached_at' => now()->subDays(100),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.family', 'Araceae');

        Http::assertSentCount(1);
        $refreshed = SpeciesCache::query()->where('gbif_key', '2868241')->firstOrFail();
        $this->assertTrue($refreshed->cached_at->isAfter(now()->subMinute()));
    }

    public function test_a_stale_hit_is_served_when_gbif_is_unavailable(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'cached_at' => now()->subDays(100),
        ]);
        Http::fake(['api.gbif.org/*' => Http::failedConnection()]);

        // Stale data beats no data: a refresh failure serves the stale row, not a 503.
        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '2868241');
    }

    public function test_case_and_whitespace_variants_hit_the_same_local_row(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'cached_at' => now(),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        foreach (['MONSTERA', '  monstera  ', 'Monstera'] as $variant) {
            $this->getJson('/api/species/suggest?q='.urlencode($variant))
                ->assertOk()
                ->assertJsonPath('data.0.gbif_key', '2868241');
        }

        Http::assertNothingSent();
    }

    public function test_unicode_is_normalized_so_decomposed_input_matches_composed_names(): void
    {
        $this->actAsHousehold();
        // Stored name uses a composed (NFC) o-umlaut.
        SpeciesCache::factory()->create([
            'gbif_key' => '999',
            'scientific_name' => "Crassula ovata Sch\u{00F6}nland",
            'cached_at' => now(),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        // Query the same name with a decomposed (NFD) o + combining diaeresis.
        $this->getJson('/api/species/suggest?q='.urlencode("scho\u{0308}nland"))
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '999');

        Http::assertNothingSent();
    }

    public function test_a_null_cached_at_is_treated_as_stale_and_refreshed(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'cached_at' => null,
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.gbif_key', '2868241');

        Http::assertSentCount(1);
    }

    public function test_respects_the_requested_limit(): void
    {
        $this->actAsHousehold();
        for ($i = 0; $i < 6; $i++) {
            SpeciesCache::factory()->create([
                'gbif_key' => "k{$i}",
                'scientific_name' => "Ficus species{$i}",
                'cached_at' => now(),
            ]);
        }
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=ficus&limit=3')
            ->assertOk()
            ->assertJsonCount(3, 'data');

        Http::assertNothingSent();
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

    public function test_stale_refresh_preserves_existing_common_names(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'common_name' => 'Swiss cheese plant',
            'common_names' => ['Swiss cheese plant', 'Split-leaf philodendron'],
            'cached_at' => now()->subDays(100),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=monstera')->assertOk();

        $refreshed = SpeciesCache::query()->where('gbif_key', '2868241')->firstOrFail();
        $this->assertSame('Swiss cheese plant', $refreshed->common_name);
        $this->assertSame(['Swiss cheese plant', 'Split-leaf philodendron'], $refreshed->common_names);
    }

    public function test_local_hit_includes_common_names_in_response(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '2868241',
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'common_name' => 'Swiss cheese plant',
            'common_names' => ['Swiss cheese plant', 'Split-leaf philodendron'],
            'cached_at' => now(),
        ]);
        $this->fakeGbifMatch($this->monsteraMatch());

        $this->getJson('/api/species/suggest?q=monstera')
            ->assertOk()
            ->assertJsonPath('data.0.common_name', 'Swiss cheese plant')
            ->assertJsonPath('data.0.common_names', ['Swiss cheese plant', 'Split-leaf philodendron']);
    }

    public function test_falls_back_to_gbif_search_for_common_name_queries(): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'matchType' => 'NONE',
                'confidence' => 0,
                'synonym' => false,
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'results' => [
                    [
                        'key' => 7911643,
                        'scientificName' => 'Zamioculcas zamiifolia (Lodd.) Engl.',
                        'canonicalName' => 'Zamioculcas zamiifolia',
                        'rank' => 'SPECIES',
                        'taxonomicStatus' => 'ACCEPTED',
                        'family' => 'Araceae',
                        'vernacularNames' => [
                            ['vernacularName' => 'ZZ Plant', 'language' => 'eng'],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=ZZ Plant')
            ->assertOk()
            ->assertJsonPath('data.0.scientific_name', 'Zamioculcas zamiifolia (Lodd.) Engl.')
            ->assertJsonPath('data.0.common_name', 'ZZ Plant')
            ->assertJsonPath('data.0.family', 'Araceae');

        Http::assertSentCount(2);
        $this->assertDatabaseHas('species_cache', [
            'gbif_key' => '7911643',
            'common_name' => 'ZZ Plant',
        ]);
    }

    public function test_does_not_cascade_to_search_when_lookup_is_blocked(): void
    {
        $this->actAsHousehold();
        Http::fake(['api.gbif.org/*' => Http::failedConnection()]);

        $this->getJson('/api/species/suggest?q=ZZ Plant')
            ->assertStatus(503)
            ->assertJsonPath('code', 'search_degraded');

        // Only one call: lookup failed, breaker opened, search was skipped.
        Http::assertSentCount(1);
    }

    public function test_tries_gbif_search_when_local_results_have_no_relevant_match(): void
    {
        $this->actAsHousehold();
        SpeciesCache::factory()->create([
            'gbif_key' => '999',
            'scientific_name' => 'Bobgunnia madagascariensis (Desv.) J.H.Kirkbr. & Wiersema',
            'canonical_name' => 'Bobgunnia madagascariensis',
            'common_name' => 'Snake Bean Plant',
            'common_names' => ['Snake Bean Plant'],
            'cached_at' => now(),
        ]);
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'matchType' => 'NONE',
                'confidence' => 0,
                'synonym' => false,
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'results' => [
                    [
                        'key' => 222,
                        'scientificName' => 'Dracaena trifasciata (Prain) Mabb.',
                        'canonicalName' => 'Dracaena trifasciata',
                        'rank' => 'SPECIES',
                        'taxonomicStatus' => 'ACCEPTED',
                        'family' => 'Asparagaceae',
                        'vernacularNames' => [
                            ['vernacularName' => 'Snake Plant', 'language' => 'eng'],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=Snake Plant')
            ->assertOk()
            ->assertJsonPath('data.0.scientific_name', 'Dracaena trifasciata (Prain) Mabb.')
            ->assertJsonPath('data.0.common_name', 'Snake Plant');

        Http::assertSentCount(2);
    }

    public function test_common_name_search_empty_result_returns_empty(): void
    {
        $this->actAsHousehold();
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'matchType' => 'NONE',
                'confidence' => 0,
                'synonym' => false,
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'count' => 0,
                'results' => [],
            ]),
        ]);

        $this->getJson('/api/species/suggest?q=xyznotaplant')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Http::assertSentCount(2);
    }
}
