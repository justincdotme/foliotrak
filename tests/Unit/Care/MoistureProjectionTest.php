<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\DryingRate;
use App\Support\Care\MoistureProjection;
use App\Support\Care\SoilReading;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class MoistureProjectionTest extends TestCase
{
    /**
     * Wet soil read today at a one-point-a-day pace reaches the watering
     * threshold five days out, not today.
     *
     * @return void
     */
    public function test_a_wet_reading_pushes_the_due_date_out(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'observation'),
            new DryingRate(1.0, 'plant_median', 12),
            Carbon::parse('2026-08-14 09:00:00'),
            6,
        );

        $this->assertSame('2026-08-25', $projection->dueDate->toDateString());
    }

    /**
     * @return void
     */
    public function test_a_dry_reading_pulls_the_due_date_in(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(2.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            new DryingRate(1.0, 'plant_median', 12),
            Carbon::parse('2026-08-18 09:00:00'),
            6,
        );

        $this->assertSame('2026-08-19', $projection->dueDate->toDateString());
    }

    /**
     * A stuck-wet sensor must not silence a plant forever.
     *
     * @return void
     */
    public function test_the_deferral_is_clamped_at_twice_the_cadence(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(10.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            new DryingRate(0.05, 'plant_median', 12),
            Carbon::parse('2026-08-19 09:00:00'),
            6,
        );

        $this->assertSame('2026-08-31', $projection->dueDate->toDateString());
    }

    /**
     * @return void
     */
    public function test_the_pull_in_never_precedes_the_day_after_the_schedule_anchor(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(1.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            new DryingRate(3.0, 'plant_median', 12),
            Carbon::parse('2026-08-20 08:00:00'),
            6,
        );

        $this->assertSame('2026-08-21', $projection->dueDate->toDateString());
    }

    /**
     * @return void
     */
    public function test_the_rationale_carries_sample_size_and_stays_non_causal(): void
    {
        $rationale = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'observation'),
            new DryingRate(1.0, 'plant_median', 12),
            Carbon::parse('2026-08-14 09:00:00'),
            6,
        )->rationale;

        $this->assertStringContainsString('12', $rationale);
        $this->assertStringContainsString('8 of 10', $rationale);
        $this->assertStringNotContainsStringIgnoringCase('caused', $rationale);
        $this->assertStringNotContainsStringIgnoringCase('leads to', $rationale);
        $this->assertStringNotContainsStringIgnoringCase('because', $rationale);
    }

    /**
     * @return void
     */
    public function test_the_cadence_baseline_rationale_names_its_own_weakness(): void
    {
        $rationale = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'observation'),
            new DryingRate(0.667, 'cadence_baseline', 0),
            Carbon::parse('2026-08-14 09:00:00'),
            6,
        )->rationale;

        $this->assertStringContainsString('not enough', $rationale);
        $this->assertStringNotContainsStringIgnoringCase('caused', $rationale);
    }

    /**
     * @return void
     */
    public function test_a_sensor_anchor_says_so_in_the_rationale(): void
    {
        $rationale = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            new DryingRate(1.0, 'conditioned', 9),
            Carbon::parse('2026-08-14 09:00:00'),
            6,
        )->rationale;

        $this->assertStringContainsString('moisture sensor', $rationale);
        $this->assertStringContainsString('humidity and temperature', $rationale);
    }

    /**
     * @return void
     */
    public function test_a_zero_rate_cannot_divide_by_zero(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'observation'),
            new DryingRate(0.0, 'plant_median', 3),
            Carbon::parse('2026-08-19 09:00:00'),
            6,
        );

        $this->assertSame('2026-08-20', $projection->dueDate->toDateString());
    }
}
