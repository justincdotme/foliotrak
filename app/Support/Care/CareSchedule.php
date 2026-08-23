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
 * enforces the same four-week rule on its side. Watering then adjusts that
 * cadence against what the plant's own soil readings said about it.
 */
final readonly class CareSchedule
{
    /** Minimum days from first logged event before the median fires. */
    public const GATE_DAYS = 28;

    /**
     * @param ScheduledCareType $type
     * @param CareInterval      $interval
     * @param Carbon            $anchor
     * @param SoilHistory|null  $history  Present for watering only.
     */
    private function __construct(
        public ScheduledCareType $type,
        public CareInterval $interval,
        public Carbon $anchor,
        private ?SoilHistory $history,
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
        $override   = $type->override($plant);
        $cadence    = $override ?? self::gatedMedian($occurredAt);

        if ($cadence === null) {
            return null;
        }

        $anchor = $events->isEmpty() ? $type->scheduleStartDate($plant) : $events->last()->occurred_at;

        if ($anchor === null) {
            return null;
        }

        // A manual override fixes the interval but does not blind the schedule:
        // a reading logged since the last watering still moves this cycle.
        $history = $type === ScheduledCareType::Watering
            ? SoilHistory::for($plant, Carbon::now())
            : null;

        return new self($type, self::interval($history, $cadence, $override), $anchor, $history);
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
     * @param SoilHistory|null $history
     * @param integer          $cadenceDays
     * @param integer|null     $override
     *
     * @return CareInterval
     */
    private static function interval(?SoilHistory $history, int $cadenceDays, ?int $override): CareInterval
    {
        if ($override !== null || $history === null) {
            return CareInterval::fromCadence($cadenceDays, $override !== null ? 'override' : 'cadence');
        }

        $conditions = $history->currentConditions();

        return WateringIntervalEstimator::estimate(
            $history->evidence(),
            $cadenceDays,
            $conditions['humidity'],
            $conditions['temp'],
        );
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
     * calendar days so a clock time never shifts the day count. A soil reading
     * logged since the last watering moves this cycle's date directly; the
     * interval it is measured against is what the readings taught over time.
     *
     * @return CareDue
     */
    public function due(): CareDue
    {
        $correction = $this->correction();
        $dueDate    = $correction !== null
            ? $correction->dueDate
            : $this->anchor->copy()->addDays($this->interval->days)->startOfDay();
        $daysLeft = (int) Carbon::today()->diffInDays($dueDate, false);

        return new CareDue(
            $this->type,
            $this->interval,
            $this->interval->days,
            $dueDate,
            $daysLeft,
            DueStatus::fromDaysLeft($daysLeft),
            $correction,
        );
    }

    /**
     * @return MoistureProjection|null
     */
    private function correction(): ?MoistureProjection
    {
        $anchor = $this->history?->anchor();

        return $anchor === null
            ? null
            : MoistureProjection::from($anchor, $this->interval, $this->anchor);
    }
}
