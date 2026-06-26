<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Observation;
use App\Support\Weight;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Observation
 */
class ObservationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id' => $this->care_event_id,
            'overall_health' => $this->overall_health,
            'health_note' => $this->health_note,
            'light_level' => $this->light_level,
            'growth_rate' => $this->growth_rate?->value,
            'growth_note' => $this->growth_note,
            'leaf_size_mm' => $this->leaf_size_mm !== null ? (float) $this->leaf_size_mm : null,
            'weight_grams' => $this->weight_grams,
            'weight' => $this->weight_grams !== null ? Weight::fromGrams($this->weight_grams)->toComponents() : null,
            'symptoms' => SymptomResource::collection($this->whenLoaded('symptoms')),
        ];
    }
}
