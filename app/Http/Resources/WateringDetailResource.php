<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WateringDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WateringDetail
 */
class WateringDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id' => $this->care_event_id,
            'amount_ml' => $this->amount_ml,
        ];
    }
}
