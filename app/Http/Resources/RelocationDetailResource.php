<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RelocationDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RelocationDetail
 */
class RelocationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id' => $this->care_event_id,
            'from_location' => $this->from_location,
            'to_location' => $this->to_location,
        ];
    }
}
