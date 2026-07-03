<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Gbif\SpeciesRow;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class SpeciesRowTest extends TestCase
{
    public function test_constructs_with_required_fields(): void
    {
        $row = new SpeciesRow(gbifKey: '123', scientificName: 'Monstera deliciosa');

        $this->assertSame('123', $row->gbifKey);
        $this->assertSame('Monstera deliciosa', $row->scientificName);
        $this->assertNull($row->canonicalName);
        $this->assertNull($row->commonName);
        $this->assertNull($row->commonNames);
        $this->assertNull($row->rank);
        $this->assertNull($row->family);
        $this->assertNull($row->payload);
        $this->assertNull($row->cachedAt);
    }

    public function test_from_array_creates_a_row_from_a_complete_array(): void
    {
        $row = SpeciesRow::fromArray([
            'gbif_key' => 2868241,
            'scientific_name' => 'Monstera deliciosa Liebm.',
            'canonical_name' => 'Monstera deliciosa',
            'common_name' => 'Swiss cheese plant',
            'common_names' => ['Swiss cheese plant', 'Split-leaf philodendron'],
            'rank' => 'SPECIES',
            'family' => 'Araceae',
            'payload' => ['usageKey' => 2868241],
        ]);

        $this->assertNotNull($row);
        $this->assertSame('2868241', $row->gbifKey);
        $this->assertSame('Monstera deliciosa Liebm.', $row->scientificName);
        $this->assertSame('Monstera deliciosa', $row->canonicalName);
        $this->assertSame('Swiss cheese plant', $row->commonName);
        $this->assertSame(['Swiss cheese plant', 'Split-leaf philodendron'], $row->commonNames);
        $this->assertSame('SPECIES', $row->rank);
        $this->assertSame('Araceae', $row->family);
        $this->assertSame(['usageKey' => 2868241], $row->payload);
    }

    public function test_from_array_returns_null_when_gbif_key_is_missing(): void
    {
        $this->assertNull(SpeciesRow::fromArray([
            'scientific_name' => 'Monstera deliciosa',
        ]));
    }

    public function test_from_array_returns_null_when_scientific_name_is_missing(): void
    {
        $this->assertNull(SpeciesRow::fromArray([
            'gbif_key' => '123',
        ]));
    }

    public function test_from_array_returns_null_when_scientific_name_is_empty(): void
    {
        $this->assertNull(SpeciesRow::fromArray([
            'gbif_key' => '123',
            'scientific_name' => '',
        ]));
    }

    public function test_from_array_parses_a_string_cached_at(): void
    {
        $row = SpeciesRow::fromArray([
            'gbif_key' => '123',
            'scientific_name' => 'Monstera deliciosa',
            'cached_at' => '2026-01-15 10:30:00',
        ]);

        $this->assertNotNull($row);
        $this->assertInstanceOf(Carbon::class, $row->cachedAt);
        $this->assertSame('2026-01-15', $row->cachedAt->toDateString());
    }

    public function test_from_array_accepts_a_carbon_cached_at(): void
    {
        $carbon = Carbon::parse('2026-06-01');
        $row = SpeciesRow::fromArray([
            'gbif_key' => '123',
            'scientific_name' => 'Monstera deliciosa',
            'cached_at' => $carbon,
        ]);

        $this->assertNotNull($row);
        $this->assertSame('2026-06-01', $row->cachedAt->toDateString());
    }

    public function test_from_array_treats_null_cached_at_as_null(): void
    {
        $row = SpeciesRow::fromArray([
            'gbif_key' => '123',
            'scientific_name' => 'Monstera deliciosa',
            'cached_at' => null,
        ]);

        $this->assertNotNull($row);
        $this->assertNull($row->cachedAt);
    }

    public function test_from_array_casts_integer_gbif_key_to_string(): void
    {
        $row = SpeciesRow::fromArray([
            'gbif_key' => 2868241,
            'scientific_name' => 'Monstera deliciosa',
        ]);

        $this->assertNotNull($row);
        $this->assertSame('2868241', $row->gbifKey);
    }

    public function test_to_array_returns_the_seven_api_fields(): void
    {
        $row = new SpeciesRow(
            gbifKey: '123',
            scientificName: 'Monstera deliciosa',
            canonicalName: 'Monstera deliciosa',
            rank: 'SPECIES',
            family: 'Araceae',
            payload: ['some' => 'data'],
            cachedAt: Carbon::now(),
        );

        $array = $row->toArray();

        $this->assertSame('123', $array['gbif_key']);
        $this->assertSame('Monstera deliciosa', $array['scientific_name']);
        $this->assertSame('SPECIES', $array['rank']);
        $this->assertArrayNotHasKey('payload', $array);
        $this->assertArrayNotHasKey('cached_at', $array);
    }
}
