<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SearchDegradedException;
use App\Models\SpeciesCache;
use App\Support\Gbif\GbifClient;
use App\Support\Gbif\SpeciesRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Normalizer;

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
            throw new SearchDegradedException;
        }

        if ($records === []) {
            $records = $this->gbif->searchCommonName($query);

            if ($records === null) {
                throw new SearchDegradedException;
            }

            if ($records === []) {
                return new Collection;
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

        $items = [];

        if (isset($raw['hits']) && is_array($raw['hits'])) {
            $items = $raw['hits'];
        } elseif (isset($raw['results']) && is_array($raw['results'])) {
            $items = array_map(
                static fn (mixed $model): array => $model instanceof SpeciesCache ? $model->toSearchableArray() : [],
                $raw['results'],
            );
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): ?SpeciesRow => is_array($item) ? SpeciesRow::fromArray($item) : null,
            $items,
        )));
    }

    /**
     * @param  Collection<int, SpeciesRow>  $hits
     */
    private function hasStale(Collection $hits): bool
    {
        $threshold = now()->subDays($this->ttlDays());

        return $hits->contains(fn (SpeciesRow $hit): bool => $hit->cachedAt === null || $hit->cachedAt->lt($threshold));
    }

    /**
     * @param  list<SpeciesRow>  $records
     */
    private function backfill(array $records): void
    {
        foreach ($records as $record) {
            $attributes = [
                'scientific_name' => $record->scientificName,
                'canonical_name' => $record->canonicalName,
                'rank' => $record->rank,
                'family' => $record->family,
                'payload' => $record->payload,
                'cached_at' => now(),
            ];

            if ($record->commonName !== null) {
                $attributes['common_name'] = $record->commonName;
            }

            if ($record->commonNames !== null) {
                $attributes['common_names'] = $record->commonNames;
            }

            SpeciesCache::updateOrCreate(
                ['gbif_key' => $record->gbifKey],
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
        return $hits->contains(function (SpeciesRow $hit) use ($query): bool {
            foreach ([$hit->canonicalName, $hit->scientificName, $hit->commonName] as $value) {
                if (is_string($value) && str_contains(Str::lower($value), $query)) {
                    return true;
                }
            }

            foreach ($hit->commonNames ?? [] as $name) {
                if (str_contains(Str::lower($name), $query)) {
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
