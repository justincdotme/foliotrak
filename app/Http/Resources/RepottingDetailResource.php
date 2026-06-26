<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RepottingDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RepottingDetail
 */
class RepottingDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id' => $this->care_event_id,
            'soil_recipe' => $this->soil_recipe,
            'pot_size_value' => $this->pot_size_value !== null ? (float) $this->pot_size_value : null,
            'pot_size_unit' => $this->pot_size_unit,
            'fertilizer_added' => $this->fertilizer_added,
        ];
    }
}
