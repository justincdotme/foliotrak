<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\RecommendationEngine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plant
 */
class RecommendationResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return RecommendationEngine::forPlant($this->resource);
    }
}
