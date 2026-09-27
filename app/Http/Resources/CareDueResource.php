<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\Care\CareDue;
use App\Support\Care\DueRationale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One due entry for a plant that already carries its own identity.
 *
 * @mixin CareDue
 */
class CareDueResource extends JsonResource
{
    /**
     * Always present, so no surface has to guess why a date says what it does.
     * The reading fields are null when nothing has been logged since the last
     * watering and the date is the interval countdown alone.
     *
     * @param CareDue $due
     *
     * @return array<string, mixed>
     */
    public static function basis(CareDue $due): array
    {
        return [
            'key'          => $due->interval->basis,
            'sample_size'  => $due->interval->sampleSize,
            'cadence_days' => $due->interval->cadenceDays,
            'learned_days' => $due->interval->learnedDays,
            'rationale'    => DueRationale::for($due->interval, $due->moisture),
            'reading'      => $due->moisture === null ? null : round($due->moisture->anchor->value, 1),
            'read_at'      => $due->moisture?->anchor->at->format('Y-m-d'),
            'source'       => $due->moisture?->anchor->source,
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
            'basis'    => self::basis($this->resource),
        ];
    }
}
