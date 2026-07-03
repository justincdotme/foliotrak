<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Care\CareDue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plant's own due entry. Identity-free: the plant is already known wherever
 * this renders (FOL-72).
 *
 * @mixin CareDue
 */
class CareDueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'due_date' => $this->dueDate->format('Y-m-d'),
            'type' => $this->type->value,
            'daysLeft' => $this->daysLeft,
            'interval' => $this->intervalDays,
        ];
    }
}
