<?php

declare(strict_types=1);

namespace App\Support\Care;

use Illuminate\Support\Carbon;

/**
 * When the soil is projected to reach watering level, from the newest reading
 * and the plant's inferred drying rate. Clamped either side of the plain
 * cadence so one stuck sensor can neither silence a plant nor nag about one.
 */
final readonly class MoistureProjection
{
    /**
     * @param Carbon      $dueDate
     * @param SoilReading $anchor
     * @param DryingRate  $rate
     * @param string      $rationale
     */
    private function __construct(
        public Carbon $dueDate,
        public SoilReading $anchor,
        public DryingRate $rate,
        public string $rationale,
    ) {}

    /**
     * @param SoilReading $anchor
     * @param DryingRate  $rate
     * @param Carbon      $scheduleAnchor The last logged event, or the manual start date.
     * @param integer     $cadenceDays
     *
     * @return self
     */
    public static function from(SoilReading $anchor, DryingRate $rate, Carbon $scheduleAnchor, int $cadenceDays): self
    {
        $daysToThreshold = $rate->perDay > 0.0
            ? ($anchor->value - DryingRateEstimator::WATER_AT) / $rate->perDay
            : 0.0;

        $projected = $anchor->at->copy()
            ->addSeconds((int) round($daysToThreshold * 86400))
            ->startOfDay();

        $earliest = $scheduleAnchor->copy()->addDay()->startOfDay();
        $latest   = $scheduleAnchor->copy()->addDays(2 * $cadenceDays)->startOfDay();

        $dueDate = $projected->lessThan($earliest) ? $earliest->copy() : $projected;
        $dueDate = $dueDate->greaterThan($latest) ? $latest->copy() : $dueDate;

        return new self($dueDate, $anchor, $rate, self::rationale($anchor, $rate, $dueDate));
    }

    /**
     * @param SoilReading $anchor
     * @param DryingRate  $rate
     * @param Carbon      $dueDate
     *
     * @return string
     */
    private static function rationale(SoilReading $anchor, DryingRate $rate, Carbon $dueDate): string
    {
        $reading = sprintf(
            'Soil read %s of 10 on %s%s.',
            self::trim($anchor->value),
            $anchor->at->format('M j'),
            $anchor->source === 'sensor' ? ' from a moisture sensor' : '',
        );

        $pace = match ($rate->basis) {
            'conditioned' => sprintf(
                ' At this plant\'s observed pace in similar humidity and temperature, about %s a day from %d readings,',
                self::points($rate->perDay),
                $rate->sampleSize,
            ),
            'humidity_banded' => sprintf(
                ' At this plant\'s observed pace at similar humidity, about %s a day from %d readings,',
                self::points($rate->perDay),
                $rate->sampleSize,
            ),
            'plant_median' => sprintf(
                ' At this plant\'s observed pace, about %s a day from %d readings,',
                self::points($rate->perDay),
                $rate->sampleSize,
            ),
            default => sprintf(
                ' There is not enough paired moisture history yet to measure how fast this plant dries, so this uses your logged cadence, about %s a day,',
                self::points($rate->perDay),
            ),
        };

        return $reading . $pace . sprintf(' that reaches watering level around %s.', $dueDate->format('M j'));
    }

    /**
     * @param float $perDay
     *
     * @return string
     */
    private static function points(float $perDay): string
    {
        return self::trim($perDay) . ' point' . (abs($perDay - 1.0) < 0.05 ? '' : 's');
    }

    /**
     * @param float $value
     *
     * @return string
     */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
