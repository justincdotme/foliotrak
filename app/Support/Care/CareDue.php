<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Models\Plant;
use Illuminate\Support\Carbon;

/**
 * The due state of one care schedule: what is due, when and how urgently.
 * Carries no plant identity; the serialization edge attaches that where a
 * cross-plant list needs it.
 */
final readonly class CareDue
{
    /**
     * @param ScheduledCareType       $type
     * @param CareInterval            $interval     What the proposed interval rests on.
     * @param integer                 $intervalDays Mirrors $interval->days for readers that only need the number.
     * @param Carbon                  $dueDate
     * @param integer                 $daysLeft
     * @param DueStatus               $status
     * @param MoistureProjection|null $moisture     When set, a reading logged since the last
     *                                              watering moved this cycle's date.
     */
    public function __construct(
        public ScheduledCareType $type,
        public CareInterval $interval,
        public int $intervalDays,
        public Carbon $dueDate,
        public int $daysLeft,
        public DueStatus $status,
        public ?MoistureProjection $moisture = null,
    ) {}

    /**
     * @param Plant             $plant
     * @param ScheduledCareType $type
     *
     * @return self|null
     */
    public static function for(Plant $plant, ScheduledCareType $type): ?self
    {
        return $plant->careDue($type);
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
