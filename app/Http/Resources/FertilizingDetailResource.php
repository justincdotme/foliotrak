<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FertilizingDetail
 */
class FertilizingDetailResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id'      => $this->care_event_id,
            'fertilizer_form_id' => $this->fertilizer_form_id,
            'form'               => $this->fertilizerForm->key,
            'brand'              => $this->brand,
            'product'            => $this->product,
            'npk_n'              => $this->npk_n !== null ? (float) $this->npk_n : null,
            'npk_p'              => $this->npk_p !== null ? (float) $this->npk_p : null,
            'npk_k'              => $this->npk_k !== null ? (float) $this->npk_k : null,
            'dose_pct'           => $this->dose_pct,
            'amount_ml'          => $this->amount_ml,
            'nutrients'          => NutrientComponentResource::collection($this->whenLoaded('nutrients')),
        ];
    }
}
