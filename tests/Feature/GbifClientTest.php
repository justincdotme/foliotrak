<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Gbif\GbifClient;
use App\Support\Gbif\SpeciesRow;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GbifClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the live GBIF API.
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeMatch(array $body): void
    {
        Http::fake(['api.gbif.org/*' => Http::response($body)]);
    }

    private function client(): GbifClient
    {
        return new GbifClient;
    }

    public function test_normalizes_a_fuzzy_match(): void
    {
        $this->fakeMatch([
            'usageKey' => 2868241,
            'scientificName' => 'Monstera deliciosa Liebm.',
            'canonicalName' => 'Monstera deliciosa',
            'rank' => 'SPECIES',
            'status' => 'ACCEPTED',
            'confidence' => 95,
            'matchType' => 'FUZZY',
            'family' => 'Araceae',
        ]);

        $records = $this->client()->lookup('monstera delicosa');

        $this->assertCount(1, $records);
        $this->assertSame('2868241', $records[0]->gbifKey);
        $this->assertSame('Monstera deliciosa Liebm.', $records[0]->scientificName);
        $this->assertSame('Araceae', $records[0]->family);
        $this->assertNull($records[0]->commonName);
    }

    #[DataProvider('confidenceCases')]
    public function test_applies_the_confidence_threshold(int $confidence, int $expectedCount): void
    {
        $this->fakeMatch([
            'usageKey' => 1,
            'scientificName' => 'Some species',
            'rank' => 'SPECIES',
            'confidence' => $confidence,
            'matchType' => 'FUZZY',
        ]);

        $this->assertCount($expectedCount, (array) $this->client()->lookup('whatever'));
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function confidenceCases(): array
    {
        return [
            'below threshold' => [79, 0],
            'at threshold' => [80, 1],
            'above threshold' => [95, 1],
        ];
    }

    #[DataProvider('unusableMatchTypes')]
    public function test_rejects_unusable_match_types(string $matchType): void
    {
        $this->fakeMatch([
            'usageKey' => 1,
            'scientificName' => 'Some taxon',
            'rank' => 'GENUS',
            'confidence' => 99,
            'matchType' => $matchType,
        ]);

        $this->assertSame([], $this->client()->lookup('whatever'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableMatchTypes(): array
    {
        return [
            'no match' => ['NONE'],
            'higher rank only' => ['HIGHERRANK'],
        ];
    }

    public function test_resolves_a_synonym_to_its_accepted_name(): void
    {
        $this->fakeMatch([
            'usageKey' => 111,
            'scientificName' => 'Sansevieria trifasciata Prain',
            'canonicalName' => 'Sansevieria trifasciata',
            'rank' => 'SPECIES',
            'status' => 'SYNONYM',
            'confidence' => 97,
            'matchType' => 'FUZZY',
            'acceptedUsageKey' => 222,
            'accepted' => 'Dracaena trifasciata (Prain) Mabb.',
            'family' => 'Asparagaceae',
        ]);

        $records = $this->client()->lookup('sanseveria trifasciata');

        $this->assertSame('222', $records[0]->gbifKey);
        $this->assertSame('Dracaena trifasciata (Prain) Mabb.', $records[0]->scientificName);
    }

    public function test_includes_confident_alternatives_and_drops_weak_ones(): void
    {
        $this->fakeMatch([
            'usageKey' => 1,
            'scientificName' => 'Primary species',
            'rank' => 'SPECIES',
            'confidence' => 95,
            'matchType' => 'FUZZY',
            'alternatives' => [
                ['usageKey' => 2, 'scientificName' => 'Confident alt', 'rank' => 'SPECIES', 'confidence' => 90, 'matchType' => 'FUZZY'],
                ['usageKey' => 3, 'scientificName' => 'Weak alt', 'rank' => 'SPECIES', 'confidence' => 40, 'matchType' => 'FUZZY'],
            ],
        ]);

        $records = $this->client()->lookup('whatever');

        $this->assertSame(['1', '2'], array_map(fn (SpeciesRow $r) => $r->gbifKey, $records));
    }

    public function test_throttle_saturation_returns_null_without_forwarding(): void
    {
        config()->set('services.gbif.throttle.max_attempts', 1);
        $this->fakeMatch([
            'usageKey' => 1, 'scientificName' => 'X', 'rank' => 'SPECIES', 'confidence' => 95, 'matchType' => 'EXACT',
        ]);

        $this->assertNotNull($this->client()->lookup('alpha'));
        $this->assertNull($this->client()->lookup('beta'));
        Http::assertSentCount(1);
    }

    public function test_breaker_opens_after_a_failure_and_skips_during_cooldown(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('error', 500)]);

        $this->assertNull($this->client()->lookup('alpha'));
        $this->assertNull($this->client()->lookup('beta'));
        Http::assertSentCount(1);
    }

    public function test_breaker_cooldown_grows_on_successive_failures(): void
    {
        Http::fake(['api.gbif.org/*' => Http::response('error', 500)]);

        $this->client()->lookup('a');
        $this->travel(31)->seconds();
        $this->client()->lookup('b');
        Http::assertSentCount(2);

        // 31s into the second (doubled, 60s) window the breaker is still open.
        $this->travel(31)->seconds();
        $this->client()->lookup('c');
        Http::assertSentCount(2);
    }

    public function test_breaker_resets_after_a_success(): void
    {
        Http::fake(['api.gbif.org/*' => Http::sequence()
            ->push('error', 500)
            ->push(['usageKey' => 1, 'scientificName' => 'X', 'rank' => 'SPECIES', 'confidence' => 95, 'matchType' => 'EXACT'])
            ->push('error', 500)
            ->push(['usageKey' => 2, 'scientificName' => 'Y', 'rank' => 'SPECIES', 'confidence' => 95, 'matchType' => 'EXACT'])]);

        $this->assertNull($this->client()->lookup('a'));
        $this->travel(31)->seconds();
        $this->assertNotNull($this->client()->lookup('b'));
        $this->assertNull($this->client()->lookup('c'));
        // A reset means c's window is the base 30s again, expired by t+31, so d forwards.
        $this->travel(31)->seconds();
        $this->assertNotNull($this->client()->lookup('d'));
        Http::assertSentCount(4);
    }

    public function test_searches_common_name_via_species_search_endpoint(): void
    {
        Http::fake([
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'count' => 1,
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
                            ['vernacularName' => 'Zanzibar gem', 'language' => 'eng'],
                            ['vernacularName' => 'Planta ZZ', 'language' => 'spa'],
                        ],
                    ],
                ],
            ]),
        ]);

        $records = $this->client()->searchCommonName('zz plant');

        $this->assertCount(1, $records);
        $this->assertSame('7911643', $records[0]->gbifKey);
        $this->assertSame('Zamioculcas zamiifolia (Lodd.) Engl.', $records[0]->scientificName);
        $this->assertSame('ZZ Plant', $records[0]->commonName);
        $this->assertSame(['ZZ Plant', 'Zanzibar gem'], $records[0]->commonNames);
        $this->assertSame('Araceae', $records[0]->family);
    }

    public function test_search_resolves_synonym_to_accepted_name(): void
    {
        Http::fake([
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0,
                'limit' => 5,
                'endOfRecords' => true,
                'results' => [
                    [
                        'key' => 111,
                        'scientificName' => 'Sansevieria trifasciata Prain',
                        'canonicalName' => 'Sansevieria trifasciata',
                        'rank' => 'SPECIES',
                        'taxonomicStatus' => 'SYNONYM',
                        'acceptedKey' => 222,
                        'accepted' => 'Dracaena trifasciata (Prain) Mabb.',
                        'family' => 'Asparagaceae',
                        'vernacularNames' => [
                            ['vernacularName' => 'Snake Plant', 'language' => 'eng'],
                        ],
                    ],
                ],
            ]),
        ]);

        $records = $this->client()->searchCommonName('snake plant');

        $this->assertSame('222', $records[0]->gbifKey);
        $this->assertSame('Dracaena trifasciata (Prain) Mabb.', $records[0]->scientificName);
        $this->assertSame('Snake Plant', $records[0]->commonName);
    }

    public function test_search_deduplicates_by_resolved_key(): void
    {
        Http::fake([
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
                        'vernacularNames' => [['vernacularName' => 'Snake Plant', 'language' => 'eng']],
                    ],
                    [
                        'key' => 111,
                        'scientificName' => 'Sansevieria trifasciata Prain',
                        'rank' => 'SPECIES',
                        'taxonomicStatus' => 'SYNONYM',
                        'acceptedKey' => 222,
                        'accepted' => 'Dracaena trifasciata (Prain) Mabb.',
                        'family' => 'Asparagaceae',
                        'vernacularNames' => [['vernacularName' => 'Snake Plant', 'language' => 'eng']],
                    ],
                ],
            ]),
        ]);

        $records = $this->client()->searchCommonName('snake plant');

        $this->assertCount(1, $records);
        $this->assertSame('222', $records[0]->gbifKey);
    }

    public function test_search_shares_throttle_and_breaker_with_lookup(): void
    {
        config()->set('services.gbif.throttle.max_attempts', 1);
        Http::fake([
            'api.gbif.org/v1/species/match*' => Http::response([
                'usageKey' => 1, 'scientificName' => 'X', 'rank' => 'SPECIES',
                'confidence' => 95, 'matchType' => 'EXACT',
            ]),
            'api.gbif.org/v1/species/search*' => Http::response([
                'offset' => 0, 'limit' => 5, 'endOfRecords' => true, 'results' => [],
            ]),
        ]);

        $this->assertNotNull($this->client()->lookup('alpha'));
        $this->assertNull($this->client()->searchCommonName('beta'));
        Http::assertSentCount(1);
    }
}
