<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SpeciesCache;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpeciesCache
 */
class SpeciesSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'gbif_key' => $this->gbif_key,
            'scientific_name' => $this->scientific_name,
            'canonical_name' => $this->canonical_name,
            'common_name' => $this->common_name,
            'rank' => $this->rank,
            'family' => $this->family,
        ];
    }
}
