<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Plant;
use App\Support\Care\CareDue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plant
 */
class PlantResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $condition
     * @param  list<CareDue>  $dueForCare
     */
    public function __construct(Plant $plant, private readonly array $condition, private readonly array $dueForCare)
    {
        parent::__construct($plant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'common_name' => $this->common_name,
            'scientific_name' => $this->scientific_name,
            'nickname' => $this->nickname,
            'gbif_key' => $this->gbif_key,
            'location' => $this->location ? ['id' => $this->location->id, 'name' => $this->location->name] : null,
            'acquired_on' => $this->acquired_on?->format('Y-m-d'),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'watering_interval_days_override' => $this->watering_interval_days_override,
            'watering_schedule_start_date' => $this->watering_schedule_start_date?->format('Y-m-d'),
            'fertilizing_interval_days_override' => $this->fertilizing_interval_days_override,
            'cover_photo_id' => $this->cover_photo_id,
            'cover_photo' => $this->coverPhoto ? new PhotoResource($this->coverPhoto) : null,
            'condition' => $this->condition,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'equipment' => EquipmentResource::collection($this->whenLoaded('equipment')),
            'sensors' => $this->whenLoaded('sensors', fn () => $this->sensors->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color,
                'location' => $s->location,
            ])),
            'due_for_care' => CareDueResource::collection($this->dueForCare),
            'last_watered_at' => $this->wateringEvents->last()?->occurred_at?->toISOString(),
        ];
    }
}
