<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Models\CareEvent;
use App\Models\Plant;
use Illuminate\Support\Carbon;

/**
 * A plant's schedule for one care type: the interval and the anchor its next
 * due date counts from. The interval is the manual override when set, else the
 * median gap between logged events, and a median only fires once 28 days have
 * passed since the type's first logged event: below that a two-event median
 * asserts a cadence it never observed (FOL-98). The recommendation engine
 * enforces the same four-week rule on its side.
 */
final readonly class CareSchedule
{
    /** Minimum days from first logged event before the median fires. */
    public const GATE_DAYS = 28;

    /**
     * @param ScheduledCareType $type
     * @param integer           $intervalDays
     * @param Carbon            $anchor
     */
    private function __construct(
        public ScheduledCareType $type,
        public int $intervalDays,
        public Carbon $anchor,
    ) {}

    /**
     * @param Plant             $plant
     * @param ScheduledCareType $type
     *
     * @return self|null
     */
    public static function for(Plant $plant, ScheduledCareType $type): ?self
    {
        $events     = $type->events($plant);
        $occurredAt = $events->map(fn (CareEvent $event): Carbon => $event->occurred_at)->all();

        $interval = $type->override($plant) ?? self::gatedMedian($occurredAt);

        if ($interval === null) {
            return null;
        }

        $anchor = $events->isEmpty() ? $type->scheduleStartDate($plant) : $events->last()->occurred_at;

        if ($anchor === null) {
            return null;
        }

        return new self($type, $interval, $anchor);
    }

    /**
     * The raw median gap, ungated: the recommendation engine's four-week
     * baseline and recent windows are narrower than the gate by construction,
     * so they need the median without it.
     *
     * @param list<Carbon> $occurredAt
     *
     * @return integer|null
     */
    public static function medianGapDays(array $occurredAt): ?int
    {
        if (count($occurredAt) < 2) {
            return null;
        }

        $timestamps = array_map(fn (Carbon $date): int => $date->getTimestamp(), $occurredAt);
        sort($timestamps);

        $gaps = [];

        for ($i = 1; $i < count($timestamps); $i++) {
            $gaps[] = ($timestamps[$i] - $timestamps[$i - 1]) / 86400;
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 === 1
            ? $gaps[$middle]
            : ($gaps[$middle - 1] + $gaps[$middle]) / 2;

        // A zero or sub-day median would read as perpetually due; floor at one day.
        return max(1, (int) round($median));
    }

    /**
     * @param list<Carbon> $occurredAt
     *
     * @return integer|null
     */
    private static function gatedMedian(array $occurredAt): ?int
    {
        if ($occurredAt === []) {
            return null;
        }

        /** @var Carbon $first */
        $first = collect($occurredAt)->min();

        if ((int) $first->copy()->startOfDay()->diffInDays(Carbon::today()) < self::GATE_DAYS) {
            return null;
        }

        return self::medianGapDays($occurredAt);
    }

    /**
     * The state of this schedule against today, in midnight-normalized
     * calendar days so a clock time never shifts the day count.
     *
     * @return CareDue
     */
    public function due(): CareDue
    {
        $dueDate  = $this->anchor->copy()->addDays($this->intervalDays)->startOfDay();
        $daysLeft = (int) Carbon::today()->diffInDays($dueDate, false);

        return new CareDue($this->type, $this->intervalDays, $dueDate, $daysLeft, DueStatus::fromDaysLeft($daysLeft));
    }
}
