<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RelocationDetail
 */
class RelocationDetailResource extends JsonResource
{
    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'care_event_id' => $this->care_event_id,
            'from_location' => $this->fromLocation ? ['id' => $this->fromLocation->id, 'name' => $this->fromLocation->name] : null,
            'to_location'   => $this->toLocation ? ['id' => $this->toLocation->id, 'name' => $this->toLocation->name] : null,
        ];
    }
}
