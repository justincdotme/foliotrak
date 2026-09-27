<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\DryingRateEstimator;
use App\Support\Care\SoilReading;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class DryingRateEstimatorTest extends TestCase
{
    /**
     * An unbroken fall is one run measured end to end, not a run per pair.
     *
     * @return void
     */
    public function test_an_unbroken_fall_is_one_run_measured_end_to_end(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(9.0, '2026-08-10'),
            $this->reading(7.0, '2026-08-12'),
            $this->reading(5.0, '2026-08-14'),
        ], []);

        $this->assertCount(1, $runs);
        $this->assertSame(1.0, $runs[0]->perDay);
    }

    /**
     * On a scale that moves in whole points, most days are flat. Measuring
     * each pair and keeping only the days that fell reports the steepest one
     * as if it were every day.
     *
     * @return void
     */
    public function test_a_mostly_flat_series_reports_its_true_slope(): void
    {
        $readings = [];
        $value    = 6.0;

        for ($day = 0; $day < 20; $day++) {
            if ($day === 10) {
                $value--;
            }

            $readings[] = $this->reading($value, Carbon::parse('2026-08-01')->addDays($day)->toDateString());
        }

        $runs = DryingRateEstimator::runs($readings, []);

        $this->assertCount(1, $runs);
        $this->assertEqualsWithDelta(1 / 19, $runs[0]->perDay, 0.001);
    }

    /**
     * A hand-entered reading and a calibrated probe are different instruments.
     * Pairing them manufactures drying that never happened.
     *
     * @return void
     */
    public function test_runs_never_span_two_sources(): void
    {
        $runs = DryingRateEstimator::runs([
            new SoilReading(8.0, Carbon::parse('2026-08-13'), 'observation'),
            new SoilReading(5.0, Carbon::parse('2026-08-14'), 'sensor'),
        ], []);

        $this->assertSame([], $runs);
    }

    /**
     * A watering resets the soil, so a span crossing one is not a drying run.
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
     * Soil that rose without a logged watering ends the run it interrupts, so
     * an unlogged watering never flattens a real stretch of drying.
     *
     * @return void
     */
    public function test_a_rise_in_moisture_ends_the_run(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(8.0, '2026-08-10'),
            $this->reading(6.0, '2026-08-12'),
            $this->reading(9.0, '2026-08-13'),
            $this->reading(7.0, '2026-08-15'),
        ], []);

        $this->assertCount(2, $runs);
        $this->assertSame(1.0, $runs[0]->perDay);
        $this->assertSame(1.0, $runs[1]->perDay);
    }

    /**
     * @return void
     */
    public function test_a_flat_run_is_not_a_drying_run(): void
    {
        $runs = DryingRateEstimator::runs([
            $this->reading(8.0, '2026-08-10'),
            $this->reading(8.0, '2026-08-14'),
        ], []);

        $this->assertSame([], $runs);
    }

    /**
     * @return void
     */
    public function test_readings_closer_together_than_the_minimum_span_are_discarded(): void
    {
        $runs = DryingRateEstimator::runs([
            new SoilReading(8.0, Carbon::parse('2026-08-10 09:00:00'), 'sensor'),
            new SoilReading(7.0, Carbon::parse('2026-08-10 12:00:00'), 'sensor'),
        ], []);

        $this->assertSame([], $runs);
    }

    /**
     * Ambient conditions are averaged across the whole run, so a correlation
     * pair describes the air the soil actually dried in.
     *
     * @return void
     */
    public function test_a_run_carries_the_mean_conditions_it_dried_in(): void
    {
        $runs = DryingRateEstimator::runs([
            new SoilReading(9.0, Carbon::parse('2026-08-10'), 'sensor', 40.0, 20.0),
            new SoilReading(7.0, Carbon::parse('2026-08-12'), 'sensor', 60.0, 24.0),
        ], []);

        $this->assertCount(1, $runs);
        $this->assertSame(50.0, $runs[0]->humidityPct);
        $this->assertSame(22.0, $runs[0]->tempC);
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
        return new SoilReading($value, Carbon::parse($date), 'sensor');
    }
}
