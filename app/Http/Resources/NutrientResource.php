<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Nutrient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Nutrient
 */
class NutrientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'nutrient_id' => $this->id,
            'nutrient_key' => $this->key,
            'nutrient_label' => $this->label,
            'nutrient_symbol' => $this->symbol,
        ];
    }
}
