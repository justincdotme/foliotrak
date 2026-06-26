<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FertilizerForm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FertilizerForm
 */
class FertilizerFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
        ];
    }
}
