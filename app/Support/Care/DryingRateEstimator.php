<?php

declare(strict_types=1);

namespace App\Support\Care;

use Illuminate\Support\Carbon;

/**
 * Measures how fast a plant's soil actually dried, in points of the 1-to-10
 * scale per day, from its own readings. It answers how fast, never how often;
 * the runs it produces are what the correlation views plot drying against
 * humidity and temperature.
 */
final class DryingRateEstimator
{
    /** Scale position at which the plant is treated as needing water. */
    public const WATER_AT = 3.0;

    /** Scale position treated as freshly watered. */
    public const WET = 7.0;

    /** A stretch shorter than this cannot separate drying from sensor noise. */
    private const MIN_RUN_DAYS = 0.5;

    /**
     * Drying stretches, one per source per span between waterings. The rate is
     * the whole drop over the whole span. Taking the median of day-to-day
     * deltas and dropping the flat ones reports the steepest day as if it were
     * every day, which on a scale that moves in whole points is most of them.
     *
     * @param list<SoilReading> $readings      Chronological.
     * @param list<Carbon>      $wateringTimes
     *
     * @return list<DryingRun>
     */
    public static function runs(array $readings, array $wateringTimes): array
    {
        $runs = [];

        foreach (self::segments($readings, $wateringTimes) as $segment) {
            $first = $segment[0];
            $last  = $segment[count($segment) - 1];
            $days  = ($last->at->getTimestamp() - $first->at->getTimestamp()) / 86400;

            if ($days < self::MIN_RUN_DAYS) {
                continue;
            }

            $perDay = ($first->value - $last->value) / $days;

            if ($perDay <= 0.0) {
                continue;
            }

            $runs[] = new DryingRun(
                perDay: $perDay,
                humidityPct: self::meanOver($segment, fn (SoilReading $r): ?float => $r->humidityPct),
                tempC: self::meanOver($segment, fn (SoilReading $r): ?float => $r->tempC),
            );
        }

        return $runs;
    }

    /**
     * @param float $humidityPct
     *
     * @return string
     */
    public static function humidityBand(float $humidityPct): string
    {
        return match (true) {
            $humidityPct < 40.0 => 'low',
            $humidityPct > 60.0 => 'high',
            default             => 'mid',
        };
    }

    /**
     * @param float $tempC
     *
     * @return string
     */
    public static function tempBand(float $tempC): string
    {
        return match (true) {
            $tempC < 18.0 => 'cool',
            $tempC > 24.0 => 'warm',
            default       => 'mild',
        };
    }

    /**
     * Unbroken stretches of drying. A hand-entered reading and a calibrated
     * probe are different instruments, so a segment never spans both, and a
     * watering or a rise in moisture ends the one it interrupts.
     *
     * @param list<SoilReading> $readings
     * @param list<Carbon>      $wateringTimes
     *
     * @return list<list<SoilReading>>
     */
    private static function segments(array $readings, array $wateringTimes): array
    {
        $segments = [];
        $current  = [];

        foreach ($readings as $reading) {
            $previous = $current === [] ? null : $current[count($current) - 1];

            $broken = $previous !== null && (
                $previous->source !== $reading->source
                || $reading->value > $previous->value
                || self::wateredBetween($wateringTimes, $previous->at, $reading->at)
            );

            if ($broken) {
                $segments[] = $current;
                $current    = [];
            }

            $current[] = $reading;
        }

        if ($current !== []) {
            $segments[] = $current;
        }

        return array_values(array_filter($segments, fn (array $segment): bool => count($segment) > 1));
    }

    /**
     * @param list<Carbon> $wateringTimes
     * @param Carbon       $from
     * @param Carbon       $to
     *
     * @return boolean
     */
    private static function wateredBetween(array $wateringTimes, Carbon $from, Carbon $to): bool
    {
        foreach ($wateringTimes as $time) {
            if ($time->greaterThan($from) && $time->lessThanOrEqualTo($to)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<SoilReading>                   $segment
     * @param callable(SoilReading): (float|null) $read
     *
     * @return float|null
     */
    private static function meanOver(array $segment, callable $read): ?float
    {
        $present = array_values(array_filter(array_map($read, $segment), fn (?float $v): bool => $v !== null));

        return $present === [] ? null : array_sum($present) / count($present);
    }
}
