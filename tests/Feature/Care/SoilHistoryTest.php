<?php

declare(strict_types=1);

namespace Tests\Feature\Care;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use App\Support\Care\SoilHistory;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SoilHistoryTest extends TestCase
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
     * @return void
     */
    public function test_anchor_reads_the_newest_observation_moisture(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDays(3), precise: 5);
        $this->observe($plant, $this->now->copy()->subDay(), precise: 8);

        $anchor = SoilHistory::for($this->loaded($plant), $this->now)->anchor();

        $this->assertNotNull($anchor);
        $this->assertSame(8.0, $anchor->value);
        $this->assertSame('observation', $anchor->source);
    }

    /**
     * @return void
     */
    public function test_anchor_falls_back_to_the_relative_scale_when_precise_is_absent(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDay(), relative: 'wet');

        $anchor = SoilHistory::for($this->loaded($plant), $this->now)->anchor();

        $this->assertNotNull($anchor);
        $this->assertSame(8.0, $anchor->value);
    }

    /**
     * The watering modal writes a companion observation at the identical
     * occurred_at, and that reading is taken before the water goes in.
     *
     * @return void
     */
    public function test_a_reading_stamped_at_a_watering_never_anchors(): void
    {
        $plant = Plant::factory()->create();
        $at    = $this->now->copy()->subDay();
        $this->water($plant, $at);
        $this->observe($plant, $at, precise: 2);

        $this->assertNull(SoilHistory::for($this->loaded($plant), $this->now)->anchor());
    }

    /**
     * @return void
     */
    public function test_readings_older_than_the_last_watering_never_anchor(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDays(5), precise: 2);
        $this->water($plant, $this->now->copy()->subDays(2));

        $this->assertNull(SoilHistory::for($this->loaded($plant), $this->now)->anchor());
    }

    /**
     * @return void
     */
    public function test_daily_collapses_to_one_reading_per_day_and_orders_oldest_first(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDays(2)->setTime(8, 0), precise: 9);
        $this->observe($plant, $this->now->copy()->subDays(2)->setTime(20, 0), precise: 7);
        $this->observe($plant, $this->now->copy()->subDay(), precise: 5);

        $daily = SoilHistory::for($this->loaded($plant), $this->now)->daily();

        $this->assertCount(2, $daily);
        $this->assertSame(8.0, $daily[0]->value);
        $this->assertSame(5.0, $daily[1]->value);
    }

    /**
     * @return void
     */
    public function test_current_conditions_come_from_the_newest_observation_ambient_fields(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDay(), precise: 6, humidity: 44, tempC: 22.5);

        $conditions = SoilHistory::for($this->loaded($plant), $this->now)->currentConditions();

        $this->assertSame(44.0, $conditions['humidity']);
        $this->assertSame(22.5, $conditions['temp']);
    }

    /**
     * @return void
     */
    public function test_readings_beyond_the_observation_window_are_ignored(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDays(120), precise: 9);

        $this->assertNull(SoilHistory::for($this->loaded($plant), $this->now)->anchor());
        $this->assertSame([], SoilHistory::for($this->loaded($plant), $this->now)->daily());
    }

    /**
     * @return void
     */
    public function test_observations_without_any_moisture_are_skipped(): void
    {
        $plant = Plant::factory()->create();
        $this->observe($plant, $this->now->copy()->subDay(), humidity: 50);

        $this->assertNull(SoilHistory::for($this->loaded($plant), $this->now)->anchor());
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
     * @param Plant  $plant
     * @param Carbon $at
     *
     * @return void
     */
    private function water(Plant $plant, Carbon $at): void
    {
        $event = $this->event($plant, 'watering', $at);
        $event->watering()->create(['amount_ml' => 200]);
    }

    /**
     * @param Plant        $plant
     * @param Carbon       $at
     * @param integer|null $precise
     * @param string|null  $relative
     * @param integer|null $humidity
     * @param float|null   $tempC
     *
     * @return void
     */
    private function observe(
        Plant $plant,
        Carbon $at,
        ?int $precise = null,
        ?string $relative = null,
        ?int $humidity = null,
        ?float $tempC = null,
    ): void {
        $event = $this->event($plant, 'observation', $at);
        $event->observation()->create([
            'soil_moisture_precise'  => $precise,
            'soil_moisture_relative' => $relative,
            'ambient_humidity_pct'   => $humidity,
            'ambient_temp_c'         => $tempC,
        ]);
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
