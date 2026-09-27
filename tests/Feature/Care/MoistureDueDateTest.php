<?php

declare(strict_types=1);

namespace Tests\Feature\Care;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use App\Support\Care\CareDue;
use App\Support\Care\DueRationale;
use App\Support\Care\ScheduledCareType;
use App\Support\Care\SoilHistory;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MoistureDueDateTest extends TestCase
{
    use RefreshDatabase;

    /** @var Carbon The frozen clock every case is built against. */
    private Carbon $now;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        $this->now = Carbon::parse('2026-08-20 09:00:00');
        $this->travelTo($this->now);
    }

    /**
     * The reported case: a plant the plain cadence calls due today, with an
     * observation logged today saying the soil is still wet.
     *
     * @return void
     */
    public function test_a_wet_reading_today_defers_a_plant_that_would_otherwise_be_due(): void
    {
        $plant = $this->plantWateredDaysAgo(6);
        $this->observe($plant, $this->now, 8);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due);
        $this->assertGreaterThan(0, $due->daysLeft);
        $this->assertFalse($due->isDue());
        $this->assertNotNull($due->moisture);
        $this->assertStringContainsString('8 of 10', DueRationale::for($due->interval, $due->moisture));
    }

    /**
     * A dry reading pulls the date forward, but never past half an interval
     * from the watering: a plant genuinely dry two days after a soak has a
     * problem the schedule cannot fix.
     *
     * @return void
     */
    public function test_a_dry_reading_pulls_a_plant_forward_but_not_past_the_floor(): void
    {
        $plant = $this->plantWateredDaysAgo(2);
        $this->assertSame(4, CareDue::for($this->loaded($plant), ScheduledCareType::Watering)?->daysLeft);

        $this->observe($plant, $this->now, 2);
        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due);
        $this->assertSame(1, $due->daysLeft);
    }

    /**
     * The same reading later in the cycle does put the plant over the line.
     *
     * @return void
     */
    public function test_a_dry_reading_late_in_the_cycle_makes_a_plant_due(): void
    {
        $plant = $this->plantWateredDaysAgo(5);
        $this->observe($plant, $this->now, 2);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due);
        $this->assertLessThanOrEqual(0, $due->daysLeft);
        $this->assertTrue($due->isDue());
    }

    /**
     * @return void
     */
    public function test_a_plant_with_no_moisture_readings_keeps_the_plain_cadence(): void
    {
        $plant = $this->plantWateredDaysAgo(6);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due);
        $this->assertSame(0, $due->daysLeft);
        $this->assertNull($due->moisture);
    }

    /**
     * A pre-watering reading logged by the watering modal must not make the
     * plant look dry the moment it was watered.
     *
     * @return void
     */
    public function test_a_reading_logged_alongside_a_watering_does_not_project(): void
    {
        $plant = Plant::factory()->create(['watering_interval_days_override' => 6]);
        $at    = $this->now->copy()->subDay();
        $this->water($plant, $at);
        $this->observe($plant, $at, 2);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due);
        $this->assertNull($due->moisture);
        $this->assertSame(5, $due->daysLeft);
    }

    /**
     * @return void
     */
    public function test_fertilizing_is_never_projected(): void
    {
        $plant = Plant::factory()->create(['fertilizing_interval_days_override' => 14]);
        $this->event($plant, 'fertilizing', $this->now->copy()->subDays(14));
        $this->observe($plant, $this->now, 9);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Fertilizing);

        $this->assertNotNull($due);
        $this->assertNull($due->moisture);
        $this->assertSame(0, $due->daysLeft);
    }

    /**
     * @return void
     */
    public function test_the_due_entry_reports_its_basis_and_sample_size(): void
    {
        $plant = $this->plantWateredDaysAgo(6);
        $this->observe($plant, $this->now, 8);

        $due = CareDue::for($this->loaded($plant), ScheduledCareType::Watering);

        $this->assertNotNull($due?->moisture);
        $this->assertSame('override', $due->interval->basis);
        $this->assertSame(0, $due->interval->sampleSize);
        $this->assertSame('observation', $due->moisture->anchor->source);
    }

    /**
     * @param Plant $plant
     *
     * @return Plant
     */
    private function loaded(Plant $plant): Plant
    {
        return $plant->fresh(SoilHistory::RELATIONS);
    }

    /**
     * @param integer $days
     *
     * @return Plant
     */
    private function plantWateredDaysAgo(int $days): Plant
    {
        $plant = Plant::factory()->create(['watering_interval_days_override' => 6]);
        $this->water($plant, $this->now->copy()->subDays($days));

        return $plant;
    }

    /**
     * @param Plant  $plant
     * @param Carbon $at
     *
     * @return void
     */
    private function water(Plant $plant, Carbon $at): void
    {
        $this->event($plant, 'watering', $at)->watering()->create(['amount_ml' => 200]);
    }

    /**
     * @param Plant   $plant
     * @param Carbon  $at
     * @param integer $precise
     *
     * @return void
     */
    private function observe(Plant $plant, Carbon $at, int $precise): void
    {
        $this->event($plant, 'observation', $at)->observation()->create(['soil_moisture_precise' => $precise]);
    }

    /**
     * @param Plant  $plant
     * @param string $key
     * @param Carbon $at
     *
     * @return CareEvent
     */
    private function event(Plant $plant, string $key, Carbon $at): CareEvent
    {
        return CareEvent::create([
            'plant_id'           => $plant->id,
            'care_event_type_id' => CareEventType::where('key', $key)->value('id'),
            'occurred_at'        => $at,
        ]);
    }
}
