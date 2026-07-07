<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sensor
 */
class SensorResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'mac'           => $this->mac,
            'device_name'   => $this->device_name,
            'hardware_type' => $this->hardware_type,
            'name'          => $this->name,
            'color'         => $this->color,
            'location'      => $this->location,
            'type'          => $this->type->value,
            'plant_count'   => $this->plants_count ?? 0,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
