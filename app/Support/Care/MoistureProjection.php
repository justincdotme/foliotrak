<?php

declare(strict_types=1);

namespace App\Support\Care;

use Illuminate\Support\Carbon;

/**
 * This cycle's due date when a soil reading has been logged since the last
 * watering. The reading is carried forward at the pace the plant's learned
 * interval implies, then clamped either side of that interval so one reading
 * can neither silence a plant nor nag about one.
 */
final readonly class MoistureProjection
{
    /**
     * @param Carbon       $dueDate
     * @param SoilReading  $anchor
     * @param CareInterval $interval
     */
    private function __construct(
        public Carbon $dueDate,
        public SoilReading $anchor,
        public CareInterval $interval,
    ) {}

    /**
     * @param SoilReading  $anchor
     * @param CareInterval $interval
     * @param Carbon       $scheduleAnchor The last logged watering, or the manual start date.
     *
     * @return self
     */
    public static function from(SoilReading $anchor, CareInterval $interval, Carbon $scheduleAnchor): self
    {
        $perDay = (DryingRateEstimator::WET - DryingRateEstimator::WATER_AT) / max(1, $interval->days);

        $projected = $anchor->at->copy()
            ->addSeconds((int) round((($anchor->value - DryingRateEstimator::WATER_AT) / $perDay) * 86400))
            ->startOfDay();

        // Half an interval is the floor: a plant on a twelve day rhythm is
        // never told to water two days after it was watered.
        $earliest = $scheduleAnchor->copy()->addDays(max(1, (int) round($interval->days / 2)))->startOfDay();
        $latest   = $scheduleAnchor->copy()->addDays(2 * $interval->days)->startOfDay();

        $dueDate = $projected->lessThan($earliest) ? $earliest->copy() : $projected;
        $dueDate = $dueDate->greaterThan($latest) ? $latest->copy() : $dueDate;

        return new self($dueDate, $anchor, $interval);
    }
}
