<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CareEventType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CareEventType
 */
class CareEventTypeResource extends JsonResource
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
