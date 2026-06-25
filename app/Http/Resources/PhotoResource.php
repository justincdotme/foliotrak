<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Photo
 */
class PhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plant_id' => $this->plant_id,
            'care_event_id' => $this->care_event_id,
            'path' => $this->path,
            'original_filename' => $this->original_filename,
            'taken_on' => $this->taken_on->format('Y-m-d'),
            'caption' => $this->caption,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
