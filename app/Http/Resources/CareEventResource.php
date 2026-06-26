<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CareEvent;
use Closure;
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
            'watering' => $this->detail('watering', fn () => new WateringDetailResource($this->watering)),
            'fertilizing' => $this->detail('fertilizing', fn () => new FertilizingDetailResource($this->fertilizing)),
            'repotting' => $this->detail('repotting', fn () => new RepottingDetailResource($this->repotting)),
            'observation' => $this->detail('observation', fn () => new ObservationDetailResource($this->observation)),
            'relocation' => $this->detail('relocation', fn () => new RelocationDetailResource($this->relocation)),
        ];
    }

    /**
     * Emit a typed detail only when its relation is loaded and present. The timeline
     * eager-loads all five detail relations on every event, so without the null guard
     * a watering event would render the four non-matching keys as null.
     */
    private function detail(string $relation, Closure $resource): mixed
    {
        return $this->when(
            $this->relationLoaded($relation) && $this->getRelation($relation) !== null,
            $resource,
        );
    }
}
