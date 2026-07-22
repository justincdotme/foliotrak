<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FertilizingNutrient
 */
class NutrientComponentResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nutrient_id'     => $this->nutrient_id,
            'nutrient_key'    => $this->nutrient->key,
            'nutrient_label'  => $this->nutrient->label,
            'nutrient_symbol' => $this->nutrient->symbol,
            'note'            => $this->note,
        ];
    }
}
