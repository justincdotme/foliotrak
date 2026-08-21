<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Care\MoistureProjection;
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
     * Null whenever the due date is the plain cadence countdown, so the shape
     * says plainly that no reading informed it.
     *
     * @param MoistureProjection|null $projection
     *
     * @return array<string, mixed>|null
     */
    public static function moisture(?MoistureProjection $projection): ?array
    {
        if ($projection === null) {
            return null;
        }

        return [
            'reading'     => round($projection->anchor->value, 1),
            'source'      => $projection->anchor->source,
            'read_at'     => $projection->anchor->at->format('Y-m-d'),
            'basis'       => $projection->rate->basis,
            'sample_size' => $projection->rate->sampleSize,
            'rationale'   => $projection->rationale,
        ];
    }

    /**
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status'   => $this->status->value,
            'due_date' => $this->dueDate->format('Y-m-d'),
            'type'     => $this->type->value,
            'daysLeft' => $this->daysLeft,
            'interval' => $this->intervalDays,
            'moisture' => self::moisture($this->moisture),
        ];
    }
}
