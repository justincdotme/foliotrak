<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Nutrient
 */
class NutrientResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nutrient_id'     => $this->id,
            'nutrient_key'    => $this->key,
            'nutrient_label'  => $this->label,
            'nutrient_symbol' => $this->symbol,
        ];
    }
}
