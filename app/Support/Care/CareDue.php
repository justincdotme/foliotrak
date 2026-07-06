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
    /**
     * @param ScheduledCareType $type
     * @param integer           $intervalDays
     * @param Carbon            $dueDate
     * @param integer           $daysLeft
     * @param DueStatus         $status
     */
    public function __construct(
        public ScheduledCareType $type,
        public int $intervalDays,
        public Carbon $dueDate,
        public int $daysLeft,
        public DueStatus $status,
    ) {}

    /**
     * @param Plant             $plant
     * @param ScheduledCareType $type
     *
     * @return self|null
     */
    public static function for(Plant $plant, ScheduledCareType $type): ?self
    {
        return CareSchedule::for($plant, $type)?->due();
    }

    /**
     * @param Plant $plant
     *
     * @return list<self> one entry per care type with a derivable schedule
     */
    public static function forPlant(Plant $plant): array
    {
        return array_values(array_filter(array_map(
            fn (ScheduledCareType $type): ?self => self::for($plant, $type),
            ScheduledCareType::cases(),
        )));
    }

    /**
     * @return boolean
     */
    public function isDue(): bool
    {
        return $this->daysLeft <= 0;
    }

    /**
     * @return integer
     */
    public function daysOverdue(): int
    {
        return max(0, -$this->daysLeft);
    }
}
