<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SearchDegradedException;
use App\Models\SpeciesCache;
use App\Support\Gbif\GbifClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Normalizer;

/**
 * Typo-tolerant species search: Meilisearch first, GBIF's fuzzy matcher on a miss
 * (ADR-0013). Reads are served from the search index, so a typeahead keystroke is
 * one Meilisearch call with no database round-trip; MySQL is written on a backfill
 * and stays the rebuildable source of truth.
 *
 * @phpstan-type SpeciesRow array<string, mixed>
 */
class PlantSearchService
{
    public function __construct(private readonly GbifClient $gbif) {}

    /**
     * @return Collection<int, SpeciesRow>
     *
     * @throws SearchDegradedException when the query misses locally and GBIF is unavailable
     */
    public function search(string $rawQuery, int $limit): Collection
    {
        $query = $this->normalize($rawQuery);

        $hits = $this->localSearch($query, $limit);

        if ($hits->isNotEmpty()) {
            if ($this->hasStale($hits)) {
                $refreshed = $this->gbif->lookup($query);

                if (is_array($refreshed) && $refreshed !== []) {
                    $this->backfill($refreshed);

                    return collect($refreshed)->take($limit)->values();
                }
            }

            if ($this->hasRelevantMatch($hits, $query)) {
                return $hits;
            }
        }

        $records = $this->gbif->lookup($query);

        if ($records === null) {
            throw new SearchDegradedException();
        }

        if ($records === []) {
            $records = $this->gbif->searchCommonName($query);

            if ($records === null) {
                throw new SearchDegradedException();
            }

            if ($records === []) {
                return new Collection();
            }
        }

        $this->backfill($records);

        return collect($records)->take($limit)->values();
    }

    private function normalize(string $rawQuery): string
    {
        $normalized = Normalizer::normalize($rawQuery, Normalizer::FORM_C);

        if ($normalized === false) {
            $normalized = $rawQuery;
        }

        return Str::lower(Str::squish($normalized));
    }

    /**
     * @return Collection<int, SpeciesRow>
     */
    private function localSearch(string $query, int $limit): Collection
    {
        return collect($this->hitsToRows(SpeciesCache::search($query)->take($limit)->raw()));
    }

    /**
     * Meilisearch returns the indexed documents directly under `hits`; the
     * collection engine used in tests returns models under `results`, projected
     * the same way. Either path yields rows without a database hydration.
     *
     * @return list<SpeciesRow>
     */
    private function hitsToRows(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        if (isset($raw['hits']) && is_array($raw['hits'])) {
            return array_values(array_map(
                static fn (mixed $hit): array => is_array($hit) ? $hit : [],
                $raw['hits'],
            ));
        }

        if (isset($raw['results']) && is_array($raw['results'])) {
            return array_values(array_map(
                static fn (mixed $model): array => $model instanceof SpeciesCache ? $model->toSearchableArray() : [],
                $raw['results'],
            ));
        }

        return [];
    }

    /**
     * @param  Collection<int, SpeciesRow>  $hits
     */
    private function hasStale(Collection $hits): bool
    {
        $threshold = now()->subDays($this->ttlDays());

        return $hits->contains(function (array $hit) use ($threshold): bool {
            $cachedAt = $hit['cached_at'] ?? null;

            return $cachedAt === null || Carbon::parse((string) $cachedAt)->lt($threshold);
        });
    }

    /**
     * @param  list<SpeciesRow>  $records
     */
    private function backfill(array $records): void
    {
        foreach ($records as $record) {
            $attributes = [
                'scientific_name' => $record['scientific_name'],
                'canonical_name' => $record['canonical_name'],
                'rank' => $record['rank'],
                'family' => $record['family'],
                'payload' => $record['payload'],
                'cached_at' => now(),
            ];

            if (($record['common_name'] ?? null) !== null) {
                $attributes['common_name'] = $record['common_name'];
            }

            if (($record['common_names'] ?? null) !== null) {
                $attributes['common_names'] = $record['common_names'];
            }

            SpeciesCache::updateOrCreate(
                ['gbif_key' => $record['gbif_key']],
                $attributes,
            );
        }
    }

    /**
     * Local results from Meilisearch can be fuzzy noise (e.g. "Z.Z.Zhou" for
     * "ZZ Plant"). Only trust them when at least one result matches the query
     * on a name field; otherwise fall through to the GBIF cascade.
     *
     * @param  Collection<int, SpeciesRow>  $hits
     */
    private function hasRelevantMatch(Collection $hits, string $query): bool
    {
        return $hits->contains(function (array $hit) use ($query): bool {
            foreach (['canonical_name', 'scientific_name', 'common_name'] as $field) {
                $value = $hit[$field] ?? null;
                if (is_string($value) && str_contains(Str::lower($value), $query)) {
                    return true;
                }
            }

            foreach ($hit['common_names'] ?? [] as $name) {
                if (is_string($name) && str_contains(Str::lower($name), $query)) {
                    return true;
                }
            }

            return false;
        });
    }

    private function ttlDays(): int
    {
        return (int) config('services.gbif.cache_ttl_days');
    }
}
