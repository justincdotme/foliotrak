<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SoilMoistureLevel;
use App\Support\Care\CareSchedule;
use Closure;
use Illuminate\Support\Carbon;

/**
 * Health-aware watering cadence (ADR-0019). Compares a baseline window (the first four weeks)
 * to a recent window (the last four weeks): when the cadence has shifted, it recommends the
 * cadence of whichever window's observations were healthier, and otherwise falls back to the
 * plain median that reminders already use. Descriptive and non-causal, always with sample size.
 */
final class WateringScheduleRecommender
{
    private const WINDOW_DAYS = 28;

    private const STABLE_TOLERANCE_FRACTION = 0.3;

    /**
     * @param  list<Carbon>  $waterings  occurred_at timestamps, any order
     * @param  list<array{date: Carbon, health: int}>  $healthObservations
     * @param  list<int>  $amounts  non-null logged amounts in ml
     * @param  list<array{precise: int|null, relative: SoilMoistureLevel|null}>  $soilReadings  newest first, up to 3
     * @return array{interval_days: int, amount_ml: int|null, sample_size: int, health_sample_size: int, basis: string, baseline_interval_days: int|null, recent_interval_days: int|null, rationale: string}|null
     */
    public static function recommend(array $waterings, array $healthObservations, array $amounts, Carbon $earliest, Carbon $now, array $soilReadings = []): ?array
    {
        $overall = CareSchedule::medianGapDays($waterings);
        if ($overall === null) {
            return null;
        }

        $amountMedian = $amounts === [] ? null : (int) round(Stats::median($amounts) ?? 0.0);
        $sampleSize = count($waterings);
        $healthSampleSize = count($healthObservations);

        // The baseline-versus-recent comparison only holds when the two windows do not overlap,
        // which needs at least two windows of history. Below that, report the plain median.
        $hasDisjointWindows = ($now->getTimestamp() - $earliest->getTimestamp()) >= 2 * self::WINDOW_DAYS * 86400;

        $baselineCadence = null;
        $recentCadence = null;

        if ($hasDisjointWindows) {
            $baselineEnd = $earliest->copy()->addDays(self::WINDOW_DAYS);
            $recentStart = $now->copy()->subDays(self::WINDOW_DAYS);

            $baselineCadence = CareSchedule::medianGapDays(
                array_values(array_filter($waterings, fn (Carbon $w): bool => $w <= $baselineEnd))
            );
            $recentCadence = CareSchedule::medianGapDays(
                array_values(array_filter($waterings, fn (Carbon $w): bool => $w >= $recentStart))
            );

            $baselineHealths = self::healthsIn($healthObservations, fn (Carbon $d): bool => $d <= $baselineEnd);
            $recentHealths = self::healthsIn($healthObservations, fn (Carbon $d): bool => $d >= $recentStart);
            $baselineHealth = Stats::median($baselineHealths);
            $recentHealth = Stats::median($recentHealths);

            if ($baselineCadence !== null && $recentCadence !== null && $baselineHealth !== null && $recentHealth !== null) {
                $tolerance = max(1, (int) round(self::STABLE_TOLERANCE_FRACTION * $baselineCadence));

                if (abs($recentCadence - $baselineCadence) > $tolerance) {
                    if ($recentHealth < $baselineHealth) {
                        return self::withSoilAdjustment(self::result(
                            $baselineCadence,
                            $amountMedian,
                            $sampleSize,
                            $healthSampleSize,
                            'revert',
                            $baselineCadence,
                            $recentCadence,
                            sprintf(
                                'Health readings were higher (median %s of 5 from %d) when you watered about every %d days than recently at about every %d days (median %s of 5 from %d). Consider returning to about every %d days.',
                                self::fmtHealth($baselineHealth),
                                count($baselineHealths),
                                $baselineCadence,
                                $recentCadence,
                                self::fmtHealth($recentHealth),
                                count($recentHealths),
                                $baselineCadence,
                            ),
                        ), $soilReadings);
                    }

                    return self::withSoilAdjustment(self::result(
                        $recentCadence,
                        $amountMedian,
                        $sampleSize,
                        $healthSampleSize,
                        'maintain',
                        $baselineCadence,
                        $recentCadence,
                        sprintf(
                            'Health readings have held or improved (median %s of 5 from %d) at your recent cadence of about every %d days. Worth keeping.',
                            self::fmtHealth($recentHealth),
                            count($recentHealths),
                            $recentCadence,
                        ),
                    ), $soilReadings);
                }

                return self::withSoilAdjustment(self::result(
                    $overall,
                    $amountMedian,
                    $sampleSize,
                    $healthSampleSize,
                    'stable',
                    $baselineCadence,
                    $recentCadence,
                    self::steadyRationale($overall, $sampleSize, $healthObservations),
                ), $soilReadings);
            }
        }

        return self::withSoilAdjustment(self::result(
            $overall,
            $amountMedian,
            $sampleSize,
            $healthSampleSize,
            'stable',
            $baselineCadence,
            $recentCadence,
            self::medianOnlyRationale($overall, $sampleSize),
        ), $soilReadings);
    }

    /**
     * @param  list<array{date: Carbon, health: int}>  $observations
     * @param  Closure(Carbon): bool  $within
     * @return list<int>
     */
    private static function healthsIn(array $observations, Closure $within): array
    {
        return array_values(array_map(
            fn (array $o): int => $o['health'],
            array_filter($observations, fn (array $o): bool => $within($o['date'])),
        ));
    }

    /**
     * @return array{interval_days: int, amount_ml: int|null, sample_size: int, health_sample_size: int, basis: string, baseline_interval_days: int|null, recent_interval_days: int|null, rationale: string}
     */
    private static function result(int $interval, ?int $amount, int $sampleSize, int $healthSampleSize, string $basis, ?int $baseline, ?int $recent, string $rationale): array
    {
        return [
            'interval_days' => $interval,
            'amount_ml' => $amount,
            'sample_size' => $sampleSize,
            'health_sample_size' => $healthSampleSize,
            'basis' => $basis,
            'baseline_interval_days' => $baseline,
            'recent_interval_days' => $recent,
            'rationale' => $rationale,
        ];
    }

    /**
     * @param  list<array{date: Carbon, health: int}>  $observations
     */
    private static function steadyRationale(int $interval, int $sampleSize, array $observations): string
    {
        $base = sprintf('About every %d days, from %d waterings.', $interval, $sampleSize);

        $healths = array_map(fn (array $o): int => $o['health'], $observations);
        $median = Stats::median($healths);
        if ($median === null) {
            return $base;
        }

        return $base.sprintf(
            ' Your cadence has held about steady; health readings have a median of %s of 5 from %d readings.',
            self::fmtHealth($median),
            count($observations),
        );
    }

    private static function medianOnlyRationale(int $interval, int $sampleSize): string
    {
        return sprintf(
            'About every %d days, from %d waterings. There is not enough paired cadence-and-health history yet to compare periods, so this is your overall median.',
            $interval,
            $sampleSize,
        );
    }

    /**
     * @param  array{interval_days: int, amount_ml: int|null, sample_size: int, health_sample_size: int, basis: string, baseline_interval_days: int|null, recent_interval_days: int|null, rationale: string}  $result
     * @param  list<array{precise: int|null, relative: SoilMoistureLevel|null}>  $soilReadings
     * @return array{interval_days: int, amount_ml: int|null, sample_size: int, health_sample_size: int, basis: string, baseline_interval_days: int|null, recent_interval_days: int|null, rationale: string}
     */
    private static function withSoilAdjustment(array $result, array $soilReadings): array
    {
        $readings = array_slice($soilReadings, 0, 3);
        $numerics = [];
        foreach ($readings as $reading) {
            $value = self::soilNumeric($reading);
            if ($value !== null) {
                $numerics[] = $value;
            }
        }

        if ($numerics === []) {
            return $result;
        }

        $avg = array_sum($numerics) / count($numerics);
        $interval = $result['interval_days'];
        $count = count($numerics);

        if ($avg <= 3.0) {
            $fraction = min(0.20, (3.0 - $avg) / 10.0);
            $adjusted = max(1, (int) round($interval * (1.0 - $fraction)));
            $result['interval_days'] = $adjusted;
            $result['rationale'] .= sprintf(
                ' Recent soil readings suggest the plant dries out faster than the base cadence (based on %d soil reading%s).',
                $count,
                $count === 1 ? '' : 's',
            );
        } elseif ($avg >= 7.0) {
            $fraction = min(0.20, ($avg - 7.0) / 10.0);
            $adjusted = (int) round($interval * (1.0 + $fraction));
            $result['interval_days'] = $adjusted;
            $result['rationale'] .= sprintf(
                ' Recent soil readings indicate the soil retains moisture well (based on %d soil reading%s).',
                $count,
                $count === 1 ? '' : 's',
            );
        }

        return $result;
    }

    /**
     * @param  array{precise: int|null, relative: SoilMoistureLevel|null}  $reading
     */
    private static function soilNumeric(array $reading): ?float
    {
        if ($reading['precise'] !== null) {
            return (float) $reading['precise'];
        }

        return $reading['relative']?->numericValue();
    }

    private static function fmtHealth(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
