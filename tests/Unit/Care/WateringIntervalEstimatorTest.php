<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\SoilEvidence;
use App\Support\Care\WateringIntervalEstimator;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class WateringIntervalEstimatorTest extends TestCase
{
    /**
     * The owner's own description: moist at seven days on a seven day rhythm
     * means seven is too often.
     *
     * @return void
     */
    public function test_a_moist_reading_at_the_cadence_lengthens_the_interval(): void
    {
        $result = WateringIntervalEstimator::estimate(
            [$this->evidence(days: 7.0, value: 5.0)],
            cadenceDays: 7,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame(14, $result->learnedDays);
        $this->assertSame(8, $result->days);
        $this->assertSame('plant_soil', $result->basis);
        $this->assertSame(1, $result->sampleSize);
    }

    /**
     * And the mirror: dry before the rhythm is up means it is not often enough.
     *
     * @return void
     */
    public function test_a_dry_reading_before_the_cadence_shortens_the_interval(): void
    {
        $result = WateringIntervalEstimator::estimate(
            array_fill(0, 4, $this->evidence(days: 4.0, value: 2.0)),
            cadenceDays: 7,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame(3, $result->learnedDays);
        $this->assertLessThan(7, $result->days);
    }

    /**
     * @return void
     */
    public function test_a_reading_at_the_watering_threshold_confirms_the_cadence(): void
    {
        $result = WateringIntervalEstimator::estimate(
            array_fill(0, 5, $this->evidence(days: 7.0, value: 3.0)),
            cadenceDays: 7,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame(7, $result->learnedDays);
        $this->assertSame(7, $result->days);
    }

    /**
     * One reading may not rewrite a schedule, however extreme it is.
     *
     * @return void
     */
    public function test_a_single_reading_moves_the_interval_only_slightly(): void
    {
        $result = WateringIntervalEstimator::estimate(
            [$this->evidence(days: 7.0, value: 9.0)],
            cadenceDays: 7,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame(21, $result->learnedDays);
        $this->assertSame(8, $result->days);
    }

    /**
     * Of course the soil is wet the morning after watering. That describes the
     * watering, not the plant.
     *
     * @return void
     */
    public function test_a_reading_taken_within_a_day_of_watering_is_ignored(): void
    {
        $result = WateringIntervalEstimator::estimate(
            [$this->evidence(days: 0.75, value: 8.0)],
            cadenceDays: 11,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame('cadence', $result->basis);
        $this->assertSame(11, $result->days);
        $this->assertNull($result->learnedDays);
    }

    /**
     * Still moist three days into a seven day rhythm agrees with that rhythm
     * without testing it. Averaging it in would drag the estimate toward the
     * day it happened to be taken.
     *
     * @return void
     */
    public function test_a_not_yet_dry_reading_before_the_cadence_carries_no_information(): void
    {
        $result = WateringIntervalEstimator::estimate(
            [$this->evidence(days: 3.0, value: 5.0)],
            cadenceDays: 7,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame('cadence', $result->basis);
        $this->assertSame(7, $result->days);
    }

    /**
     * @return void
     */
    public function test_three_readings_in_todays_bands_reach_the_conditioned_tier(): void
    {
        $warm = array_fill(0, 3, $this->evidence(days: 4.0, value: 2.0, humidity: 30.0, temp: 28.0));
        $cool = array_fill(0, 3, $this->evidence(days: 12.0, value: 2.0, humidity: 70.0, temp: 15.0));

        $result = WateringIntervalEstimator::estimate(
            [...$warm, ...$cool],
            cadenceDays: 8,
            humidityPct: 30.0,
            tempC: 28.0,
        );

        $this->assertSame('conditioned', $result->basis);
        $this->assertSame(3, $result->sampleSize);
        $this->assertSame(3, $result->learnedDays);
        $this->assertLessThan(8, $result->days);
    }

    /**
     * The same plant in the other band proposes the other interval.
     *
     * @return void
     */
    public function test_the_opposite_conditions_select_the_opposite_evidence(): void
    {
        $warm = array_fill(0, 3, $this->evidence(days: 4.0, value: 2.0, humidity: 30.0, temp: 28.0));
        $cool = array_fill(0, 3, $this->evidence(days: 12.0, value: 2.0, humidity: 70.0, temp: 15.0));

        $result = WateringIntervalEstimator::estimate(
            [...$warm, ...$cool],
            cadenceDays: 8,
            humidityPct: 70.0,
            tempC: 15.0,
        );

        $this->assertSame('conditioned', $result->basis);
        $this->assertSame(10, $result->learnedDays);
        $this->assertGreaterThan(8, $result->days);
    }

    /**
     * @return void
     */
    public function test_temperature_alone_bands_the_evidence_when_humidity_is_absent(): void
    {
        $warm = array_fill(0, 3, $this->evidence(days: 4.0, value: 2.0, temp: 28.0));
        $cool = array_fill(0, 3, $this->evidence(days: 12.0, value: 2.0, temp: 15.0));

        $result = WateringIntervalEstimator::estimate(
            [...$warm, ...$cool],
            cadenceDays: 8,
            humidityPct: null,
            tempC: 28.0,
        );

        $this->assertSame('temperature_banded', $result->basis);
        $this->assertSame(3, $result->sampleSize);
    }

    /**
     * @return void
     */
    public function test_no_evidence_returns_the_cadence_untouched(): void
    {
        $result = WateringIntervalEstimator::estimate([], cadenceDays: 9, humidityPct: null, tempC: null);

        $this->assertSame(9, $result->days);
        $this->assertSame('cadence', $result->basis);
        $this->assertSame(0, $result->sampleSize);
        $this->assertNull($result->learnedDays);
    }

    /**
     * Evidence never moves the interval by more than the weight ceiling, even
     * with a long run of extreme readings behind it.
     *
     * @return void
     */
    public function test_the_blend_weight_is_capped(): void
    {
        $result = WateringIntervalEstimator::estimate(
            array_fill(0, 40, $this->evidence(days: 10.0, value: 9.0)),
            cadenceDays: 10,
            humidityPct: null,
            tempC: null,
        );

        $this->assertSame(30, $result->learnedDays);
        $this->assertSame(22, $result->days);
    }

    /**
     * @param float      $days
     * @param float      $value
     * @param float|null $humidity
     * @param float|null $temp
     *
     * @return SoilEvidence
     */
    private function evidence(float $days, float $value, ?float $humidity = null, ?float $temp = null): SoilEvidence
    {
        return new SoilEvidence($days, $value, Carbon::parse('2026-08-20 09:00:00'), 'observation', $humidity, $temp);
    }
}
