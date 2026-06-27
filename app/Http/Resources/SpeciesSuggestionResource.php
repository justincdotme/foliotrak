<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeciesSuggestionResource extends JsonResource
{
    /**
     * Reads from a search result row (the indexed document), which is an array,
     * so it works whether the row came from Meilisearch or a backfill.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gbif_key' => data_get($this->resource, 'gbif_key'),
            'scientific_name' => data_get($this->resource, 'scientific_name'),
            'canonical_name' => data_get($this->resource, 'canonical_name'),
            'common_name' => data_get($this->resource, 'common_name'),
            'common_names' => data_get($this->resource, 'common_names'),
            'rank' => data_get($this->resource, 'rank'),
            'family' => data_get($this->resource, 'family'),
        ];
    }
}
