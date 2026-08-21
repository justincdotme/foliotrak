<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\DryingRateEstimator;
use App\Support\Care\DryingRun;
use App\Support\Care\SoilReading;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DryingRateEstimatorTest extends TestCase
{
    /**
     * @return void
     */
    public function test_consecutive_readings_yield_one_run_per_pair(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(9.0, '2026-08-10'),
            $this->reading(7.0, '2026-08-12'),
            $this->reading(5.0, '2026-08-14'),
        ], []);

        $this->assertCount(2, $runs);
        $this->assertSame(1.0, $runs[0]->perDay);
        $this->assertSame(1.0, $runs[1]->perDay);
    }

    /**
     * A watering resets the soil, so a pair spanning one is not a drying run.
     *
     * @return void
     */
    public function test_a_watering_between_two_readings_breaks_the_run(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(8.0, '2026-08-10'),
            $this->reading(4.0, '2026-08-14'),
        ], [Carbon::parse('2026-08-12 09:00:00')]);

        $this->assertSame([], $runs);
    }

    /**
     * @return void
     */
    public function test_rehydration_and_flat_pairs_are_discarded(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(4.0, '2026-08-10'),
            $this->reading(8.0, '2026-08-12'),
            $this->reading(8.0, '2026-08-14'),
        ], []);

        $this->assertSame([], $runs);
    }

    /**
     * @return void
     */
    public function test_pairs_closer_together_than_the_minimum_span_are_discarded(): void
    {
        $runs = DryingRateEstimator::runs([
            new SoilReading(8.0, Carbon::parse('2026-08-10 09:00:00'), 'sensor'),
            new SoilReading(7.0, Carbon::parse('2026-08-10 12:00:00'), 'sensor'),
        ], []);

        $this->assertSame([], $runs);
    }

    /**
     * @return void
     */
    public function test_with_no_runs_it_falls_back_to_the_cadence_baseline(): void
    {
        $rate = DryingRateEstimator::estimate([], null, null, 8);

        $this->assertSame('cadence_baseline', $rate->basis);
        $this->assertSame(0, $rate->sampleSize);
        $this->assertEqualsWithDelta(0.5, $rate->perDay, 0.0001);
    }

    /**
     * @return void
     */
    public function test_three_runs_reach_the_plant_median(): void
    {
        $runs = [
            new DryingRun(1.0, 50.0, 21.0),
            new DryingRun(2.0, 50.0, 21.0),
            new DryingRun(3.0, 50.0, 21.0),
        ];

        $rate = DryingRateEstimator::estimate($runs, null, null, 8);

        $this->assertSame('plant_median', $rate->basis);
        $this->assertSame(3, $rate->sampleSize);
        $this->assertSame(2.0, $rate->perDay);
    }

    /**
     * @return void
     */
    public function test_two_runs_are_too_few_and_fall_through_to_the_baseline(): void
    {
        $rate = DryingRateEstimator::estimate([new DryingRun(1.0), new DryingRun(3.0)], null, null, 8);

        $this->assertSame('cadence_baseline', $rate->basis);
    }

    /**
     * @return void
     */
    public function test_five_runs_in_the_matching_humidity_band_reach_humidity_banded(): void
    {
        $runs = [
            ...array_fill(0, 5, new DryingRun(2.0, 30.0, 30.0)),
            ...array_fill(0, 5, new DryingRun(0.5, 80.0, 15.0)),
        ];

        $rate = DryingRateEstimator::estimate($runs, 32.0, null, 8);

        $this->assertSame('humidity_banded', $rate->basis);
        $this->assertSame(5, $rate->sampleSize);
        $this->assertSame(2.0, $rate->perDay);
    }

    /**
     * @return void
     */
    public function test_five_runs_matching_both_bands_reach_conditioned(): void
    {
        $runs = [
            ...array_fill(0, 5, new DryingRun(2.5, 30.0, 30.0)),
            ...array_fill(0, 5, new DryingRun(1.0, 30.0, 15.0)),
        ];

        $rate = DryingRateEstimator::estimate($runs, 32.0, 28.0, 8);

        $this->assertSame('conditioned', $rate->basis);
        $this->assertSame(5, $rate->sampleSize);
        $this->assertSame(2.5, $rate->perDay);
    }

    /**
     * @return void
     */
    public function test_a_cadence_of_one_day_still_produces_a_positive_rate(): void
    {
        $this->assertGreaterThan(0.0, DryingRateEstimator::estimate([], null, null, 1)->perDay);
    }

    /**
     * @return void
     */
    public function test_the_bands_split_at_their_documented_edges(): void
    {
        $this->assertSame('low', DryingRateEstimator::humidityBand(39.9));
        $this->assertSame('mid', DryingRateEstimator::humidityBand(40.0));
        $this->assertSame('mid', DryingRateEstimator::humidityBand(60.0));
        $this->assertSame('high', DryingRateEstimator::humidityBand(60.1));

        $this->assertSame('cool', DryingRateEstimator::tempBand(17.9));
        $this->assertSame('mild', DryingRateEstimator::tempBand(18.0));
        $this->assertSame('mild', DryingRateEstimator::tempBand(24.0));
        $this->assertSame('warm', DryingRateEstimator::tempBand(24.1));
    }

    /**
     * @param float  $value
     * @param string $date
     *
     * @return SoilReading
     */
    private function reading(float $value, string $date): SoilReading
    {
        return new SoilReading($value, Carbon::parse($date . ' 09:00:00'), 'observation', 50.0, 21.0);
    }
}
