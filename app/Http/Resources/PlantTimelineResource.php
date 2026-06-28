<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\CareDueResolver;
use App\Support\Trends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plant
 */
class PlantTimelineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $observations = $this->careEvents->filter(
            fn (CareEvent $event): bool => $event->careEventType->key === 'observation'
        );

        return [
            'plant' => new PlantResource($this->resource),
            'events' => CareEventResource::collection($this->careEvents),
            'health_trend' => Trends::health($observations),
            'weight_trend' => Trends::weight($observations),
            'growth_trend' => Trends::growth($observations),
            'light_trend' => Trends::light($observations),
            'leaf_size_trend' => Trends::leafSize($observations),
            'due_for_care' => CareDueResolver::forPlant($this->resource),
            // This endpoint does not compute schedule recommendations; the contract keeps the key present.
            'recommendations' => [],
            'photos' => PhotoResource::collection($this->photos),
        ];
    }
}
