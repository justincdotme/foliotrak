<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plant
 */
class PlantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'common_name' => $this->common_name,
            'scientific_name' => $this->scientific_name,
            'gbif_key' => $this->gbif_key,
            'location' => $this->location,
            'acquired_on' => $this->acquired_on?->format('Y-m-d'),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'watering_interval_days_override' => $this->watering_interval_days_override,
            'fertilizing_interval_days_override' => $this->fertilizing_interval_days_override,
            'cover_photo_id' => $this->cover_photo_id,
            'condition' => $this->resource->condition(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
