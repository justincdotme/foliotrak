<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Support\Stats;

/**
 * Turns soil readings into a proposed watering interval. Each reading is a
 * verdict on the stretch that preceded it: wet at seven days says seven was
 * too often, dry at seven days says it was not often enough. Readings are
 * banded by the humidity and temperature they were taken in, so the same plant
 * proposes a shorter interval in warm dry air than in cool humid air. Medians
 * throughout, never a fitted model, at these sample sizes (ADR-0008).
 */
final class WateringIntervalEstimator
{
    /** Readings needed before conditioning on ambient bands. */
    private const BANDED_MIN_SAMPLES = 3;

    /**
     * A reading taken within a day of watering describes the watering, not the
     * plant: of course the soil is still wet.
     */
    private const MIN_EVIDENCE_DAYS = 1.0;

    /** Ceiling on how far soil evidence may pull the logged cadence. */
    private const MAX_WEIGHT = 0.6;

    /** Readings at which the soil evidence reaches its full weight. */
    private const FULL_WEIGHT_SAMPLES = 10;

    /** Floor on what a single reading may imply, as a multiple of its own age. */
    private const MIN_MULTIPLE = 0.5;

    /** Ceiling on the same, so an extrapolation from one point stays bounded. */
    private const MAX_MULTIPLE = 3.0;

    /**
     * @param list<SoilEvidence> $evidence
     * @param integer            $cadenceDays
     * @param float|null         $humidityPct Conditions to estimate against.
     * @param float|null         $tempC
     *
     * @return CareInterval
     */
    public static function estimate(array $evidence, int $cadenceDays, ?float $humidityPct, ?float $tempC): CareInterval
    {
        $informative = self::informative($evidence, $cadenceDays);

        [$matched, $basis] = self::select($informative, $humidityPct, $tempC);

        $learned = $matched === []
            ? null
            : Stats::median(array_map(fn (SoilEvidence $point): float => self::implied($point), $matched));

        if ($learned === null) {
            return CareInterval::fromCadence($cadenceDays);
        }

        $learnedDays = max(1, (int) round($learned));
        $weight      = min(self::MAX_WEIGHT, count($matched) / self::FULL_WEIGHT_SAMPLES);
        $blended     = max(1, (int) round($cadenceDays * (1.0 - $weight) + $learnedDays * $weight));

        return new CareInterval($blended, $basis, count($matched), $cadenceDays, $learnedDays);
    }

    /**
     * What interval one reading implies. A reading sitting at the watering
     * threshold confirms the stretch it followed; wetter extends it, drier
     * cuts it. Bounded either side so one reading cannot rewrite a schedule.
     *
     * @param SoilEvidence $evidence
     *
     * @return float
     */
    public static function implied(SoilEvidence $evidence): float
    {
        $headroom = DryingRateEstimator::WET - $evidence->value;

        if ($headroom <= 0.0) {
            return $evidence->daysSinceWatering * self::MAX_MULTIPLE;
        }

        $span    = DryingRateEstimator::WET - DryingRateEstimator::WATER_AT;
        $implied = $evidence->daysSinceWatering * $span / $headroom;

        return max(
            $evidence->daysSinceWatering * self::MIN_MULTIPLE,
            min($evidence->daysSinceWatering * self::MAX_MULTIPLE, $implied),
        );
    }

    /**
     * Readings that can move the answer. A reading still above watering level
     * on a day the plant would not have been watered anyway agrees with the
     * cadence without testing it, and averaging it in would drag the estimate
     * toward the day it happened to be taken.
     *
     * @param list<SoilEvidence> $evidence
     * @param integer            $cadenceDays
     *
     * @return list<SoilEvidence>
     */
    private static function informative(array $evidence, int $cadenceDays): array
    {
        return array_values(array_filter(
            $evidence,
            fn (SoilEvidence $point): bool => $point->daysSinceWatering >= self::MIN_EVIDENCE_DAYS
                && ($point->reachedWateringLevel() || self::implied($point) >= $cadenceDays),
        ));
    }

    /**
     * The narrowest band with enough readings behind it.
     *
     * @param list<SoilEvidence> $evidence
     * @param float|null         $humidityPct
     * @param float|null         $tempC
     *
     * @return array{0: list<SoilEvidence>, 1: string}
     */
    private static function select(array $evidence, ?float $humidityPct, ?float $tempC): array
    {
        if ($humidityPct !== null && $tempC !== null) {
            $conditioned = self::matching(
                $evidence,
                fn (SoilEvidence $point): bool => $point->humidityPct !== null
                    && $point->tempC !== null
                    && DryingRateEstimator::humidityBand($point->humidityPct) === DryingRateEstimator::humidityBand($humidityPct)
                    && DryingRateEstimator::tempBand($point->tempC) === DryingRateEstimator::tempBand($tempC),
            );

            if (count($conditioned) >= self::BANDED_MIN_SAMPLES) {
                return [$conditioned, 'conditioned'];
            }
        }

        if ($humidityPct !== null) {
            $banded = self::matching(
                $evidence,
                fn (SoilEvidence $point): bool => $point->humidityPct !== null
                    && DryingRateEstimator::humidityBand($point->humidityPct) === DryingRateEstimator::humidityBand($humidityPct),
            );

            if (count($banded) >= self::BANDED_MIN_SAMPLES) {
                return [$banded, 'humidity_banded'];
            }
        }

        if ($tempC !== null) {
            $banded = self::matching(
                $evidence,
                fn (SoilEvidence $point): bool => $point->tempC !== null
                    && DryingRateEstimator::tempBand($point->tempC) === DryingRateEstimator::tempBand($tempC),
            );

            if (count($banded) >= self::BANDED_MIN_SAMPLES) {
                return [$banded, 'temperature_banded'];
            }
        }

        return $evidence === [] ? [[], 'cadence'] : [$evidence, 'plant_soil'];
    }

    /**
     * @param list<SoilEvidence>           $evidence
     * @param callable(SoilEvidence): bool $matches
     *
     * @return list<SoilEvidence>
     */
    private static function matching(array $evidence, callable $matches): array
    {
        return array_values(array_filter($evidence, $matches));
    }
}
