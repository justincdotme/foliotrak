<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\CareInterval;
use App\Support\Care\MoistureProjection;
use App\Support\Care\SoilReading;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class MoistureProjectionTest extends TestCase
{
    /**
     * Wet soil read today on an eight day interval, which implies half a point
     * a day, reaches watering level ten days out rather than on the countdown.
     *
     * @return void
     */
    public function test_a_wet_reading_pushes_the_due_date_out(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'observation'),
            CareInterval::fromCadence(8),
            Carbon::parse('2026-08-14 09:00:00'),
        );

        $this->assertSame('2026-08-30', $projection->dueDate->toDateString());
    }

    /**
     * @return void
     */
    public function test_a_dry_reading_pulls_the_due_date_in(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(2.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            CareInterval::fromCadence(4),
            Carbon::parse('2026-08-18 09:00:00'),
        );

        $this->assertSame('2026-08-20', $projection->dueDate->toDateString());
    }

    /**
     * A sensor stuck at wet must not silence a plant forever: nineteen days
     * after watering on a six day interval, the ceiling has long since passed.
     *
     * @return void
     */
    public function test_the_deferral_is_clamped_at_twice_the_interval(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(10.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            CareInterval::fromCadence(6),
            Carbon::parse('2026-08-01 09:00:00'),
        );

        $this->assertSame('2026-08-13', $projection->dueDate->toDateString());
    }

    /**
     * The reported FOL-158 failure. A plant on a twelve day interval watered
     * two days ago cannot be due before day six, whatever the reading says.
     *
     * @return void
     */
    public function test_the_pull_in_never_precedes_half_an_interval_after_watering(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(1.0, Carbon::parse('2026-08-22 09:00:00'), 'sensor'),
            CareInterval::fromCadence(12),
            Carbon::parse('2026-08-20 08:00:00'),
        );

        $this->assertSame('2026-08-26', $projection->dueDate->toDateString());
    }

    /**
     * A one day interval still leaves a whole day between waterings.
     *
     * @return void
     */
    public function test_the_floor_is_at_least_one_day(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(1.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            CareInterval::fromCadence(1),
            Carbon::parse('2026-08-20 08:00:00'),
        );

        $this->assertSame('2026-08-21', $projection->dueDate->toDateString());
    }

    /**
     * @return void
     */
    public function test_the_anchor_and_interval_are_carried_for_the_rationale(): void
    {
        $projection = MoistureProjection::from(
            new SoilReading(8.0, Carbon::parse('2026-08-20 09:00:00'), 'sensor'),
            new CareInterval(9, 'plant_soil', 4, 7, 12),
            Carbon::parse('2026-08-14 09:00:00'),
        );

        $this->assertSame('sensor', $projection->anchor->source);
        $this->assertSame(9, $projection->interval->days);
        $this->assertSame(4, $projection->interval->sampleSize);
    }
}
