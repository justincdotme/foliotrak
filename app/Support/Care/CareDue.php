<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Models\Plant;
use Illuminate\Support\Carbon;

/**
 * The due state of one care schedule: what is due, when, and how urgently.
 * Carries no plant identity; the serialization edge attaches that where a
 * cross-plant list needs it.
 */
final readonly class CareDue
{
    public function __construct(
        public ScheduledCareType $type,
        public int $intervalDays,
        public Carbon $dueDate,
        public int $daysLeft,
        public DueStatus $status,
    ) {}

    public static function for(Plant $plant, ScheduledCareType $type): ?self
    {
        return CareSchedule::for($plant, $type)?->due();
    }

    /**
     * @return list<self> one entry per care type with a derivable schedule
     */
    public static function forPlant(Plant $plant): array
    {
        return array_values(array_filter(array_map(
            fn (ScheduledCareType $type): ?self => self::for($plant, $type),
            ScheduledCareType::cases(),
        )));
    }

    public function isDue(): bool
    {
        return $this->daysLeft <= 0;
    }

    public function daysOverdue(): int
    {
        return max(0, -$this->daysLeft);
    }
}
