<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CareEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CareEvent
 */
class CareEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'care_event_type_id' => $this->care_event_type_id,
            'type' => $this->careEventType->key,
            'occurred_at' => $this->occurred_at->toISOString(),
            'logged_by_user_id' => $this->logged_by_user_id,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'watering' => $this->whenLoaded('watering', fn () => new WateringDetailResource($this->watering)),
            'fertilizing' => $this->whenLoaded('fertilizing', fn () => new FertilizingDetailResource($this->fertilizing)),
            'repotting' => $this->whenLoaded('repotting', fn () => new RepottingDetailResource($this->repotting)),
            'observation' => $this->whenLoaded('observation', fn () => new ObservationDetailResource($this->observation)),
            'relocation' => $this->whenLoaded('relocation', fn () => new RelocationDetailResource($this->relocation)),
        ];
    }
}
