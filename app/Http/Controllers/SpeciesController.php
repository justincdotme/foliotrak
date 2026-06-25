<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SpeciesSuggestRequest;
use App\Http\Resources\SpeciesSuggestionResource;
use App\Models\SpeciesCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Http;
use Throwable;

class SpeciesController extends Controller
{
    public function suggest(SpeciesSuggestRequest $request): AnonymousResourceCollection
    {
        $query = (string) $request->string('q');
        $limit = $request->integer('limit') ?: 8;

        try {
            $response = Http::timeout(5)
                ->get((string) config('services.gbif.base_url').'/species/suggest', [
                    'q' => $query,
                    'limit' => $limit,
                ])
                ->throw();

            /** @var list<array<string, mixed>> $results */
            $results = $response->json();

            $suggestions = array_map(fn (array $record): SpeciesCache => $this->writeThrough($record), $results);

            return SpeciesSuggestionResource::collection($suggestions);
        } catch (Throwable) {
            // GBIF unreachable: fall back to the species we've already cached.
            return SpeciesSuggestionResource::collection($this->fromCache($query, $limit));
        }
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function writeThrough(array $record): SpeciesCache
    {
        return SpeciesCache::updateOrCreate(
            ['gbif_key' => (string) ($record['key'] ?? '')],
            [
                'scientific_name' => $record['scientificName'] ?? '',
                'canonical_name' => $record['canonicalName'] ?? null,
                'common_name' => $record['vernacularName'] ?? null,
                'rank' => $record['rank'] ?? null,
                'family' => $record['family'] ?? null,
                'payload' => $record,
            ],
        );
    }

    /**
     * @return Collection<int, SpeciesCache>
     */
    private function fromCache(string $query, int $limit): Collection
    {
        return SpeciesCache::query()
            ->where('scientific_name', 'like', $query.'%')
            ->orderBy('scientific_name')
            ->limit($limit)
            ->get();
    }
}
