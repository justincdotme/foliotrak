<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EquipmentChangeDetail
 */
class EquipmentChangeDetailResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id'   => $this->care_event_id,
            'equipment_id'    => $this->equipment_id,
            'equipment_label' => $this->equipment_label,
            'action'          => $this->action,
        ];
    }
}
