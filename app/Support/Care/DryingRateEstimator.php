<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Support\Stats;
use Illuminate\Support\Carbon;

/**
 * Infers how fast a plant's soil dries, in points of the 1-to-10 scale per
 * day, from the plant's own history. The estimate is conditioned on ambient
 * humidity and temperature once enough runs have accumulated in the matching
 * bands, and falls back through progressively broader bases until it reaches
 * the cadence baseline, which is always available. Medians throughout, never a
 * fitted model, at these sample sizes (ADR-0008).
 */
final class DryingRateEstimator
{
    /** Scale position at which the plant is treated as needing water. */
    public const WATER_AT = 3.0;

    /** Scale position treated as freshly watered. */
    public const WET = 7.0;

    /** Runs needed before conditioning on ambient bands. */
    private const BANDED_MIN_SAMPLES = 5;

    /** Runs needed before trusting the plant's own median. */
    private const PLANT_MIN_SAMPLES = 3;

    /** A pair closer than this cannot separate drying from sensor noise. */
    private const MIN_RUN_DAYS = 0.5;

    /**
     * Consecutive reading pairs with no watering between them.
     *
     * @param list<SoilReading> $readings      Chronological.
     * @param list<Carbon>      $wateringTimes
     *
     * @return list<DryingRun>
     */
    public static function runs(array $readings, array $wateringTimes): array
    {
        $runs = [];

        for ($i = 1; $i < count($readings); $i++) {
            $earlier = $readings[$i - 1];
            $later   = $readings[$i];

            if (self::wateredBetween($wateringTimes, $earlier->at, $later->at)) {
                continue;
            }

            $days = ($later->at->getTimestamp() - $earlier->at->getTimestamp()) / 86400;

            if ($days < self::MIN_RUN_DAYS) {
                continue;
            }

            $perDay = ($earlier->value - $later->value) / $days;

            if ($perDay <= 0.0) {
                continue;
            }

            $runs[] = new DryingRun(
                perDay: $perDay,
                humidityPct: self::mean($earlier->humidityPct, $later->humidityPct),
                tempC: self::mean($earlier->tempC, $later->tempC),
            );
        }

        return $runs;
    }

    /**
     * @param list<DryingRun> $runs
     * @param float|null      $humidityPct Conditions to estimate against.
     * @param float|null      $tempC
     * @param integer         $cadenceDays
     *
     * @return DryingRate
     */
    public static function estimate(array $runs, ?float $humidityPct, ?float $tempC, int $cadenceDays): DryingRate
    {
        if ($humidityPct !== null && $tempC !== null) {
            $conditioned = self::matching(
                $runs,
                fn (DryingRun $run): bool => $run->humidityPct !== null
                    && $run->tempC !== null
                    && self::humidityBand($run->humidityPct) === self::humidityBand($humidityPct)
                    && self::tempBand($run->tempC) === self::tempBand($tempC),
            );

            if (count($conditioned) >= self::BANDED_MIN_SAMPLES) {
                return self::medianOf($conditioned, 'conditioned');
            }
        }

        if ($humidityPct !== null) {
            $banded = self::matching(
                $runs,
                fn (DryingRun $run): bool => $run->humidityPct !== null
                    && self::humidityBand($run->humidityPct) === self::humidityBand($humidityPct),
            );

            if (count($banded) >= self::BANDED_MIN_SAMPLES) {
                return self::medianOf($banded, 'humidity_banded');
            }
        }

        if (count($runs) >= self::PLANT_MIN_SAMPLES) {
            return self::medianOf($runs, 'plant_median');
        }

        // One cadence is assumed to span one wet-to-water-at swing.
        return new DryingRate(
            perDay: (self::WET - self::WATER_AT) / max(1, $cadenceDays),
            basis: 'cadence_baseline',
            sampleSize: 0,
        );
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
     * @param list<DryingRun>           $runs
     * @param callable(DryingRun): bool $matches
     *
     * @return list<DryingRun>
     */
    private static function matching(array $runs, callable $matches): array
    {
        return array_values(array_filter($runs, $matches));
    }

    /**
     * @param list<DryingRun> $runs
     * @param string          $basis
     *
     * @return DryingRate
     */
    private static function medianOf(array $runs, string $basis): DryingRate
    {
        $median = Stats::median(array_map(fn (DryingRun $run): float => $run->perDay, $runs));

        return new DryingRate($median ?? 0.0, $basis, count($runs));
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
     * @param float|null $first
     * @param float|null $second
     *
     * @return float|null
     */
    private static function mean(?float $first, ?float $second): ?float
    {
        $present = array_values(array_filter([$first, $second], fn (?float $v): bool => $v !== null));

        return $present === [] ? null : array_sum($present) / count($present);
    }
}
