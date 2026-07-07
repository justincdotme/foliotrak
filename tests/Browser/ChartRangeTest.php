<?php

declare(strict_types=1);

use App\Enums\GrowthRate;
use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

/**
 * @return Plant
 */
function createRichPlant(): Plant
{
    $plant = Plant::factory()->create(['common_name' => 'Data Rich Plant']);

    foreach (range(0, 12) as $week) {
        $event = CareEvent::factory()->ofType('observation')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subWeeks($week),
        ]);
        Observation::create([
            'care_event_id'  => $event->id,
            'overall_health' => fake()->numberBetween(2, 5),
            'light_level'    => fake()->numberBetween(3, 9),
            'growth_rate'    => fake()->randomElement(GrowthRate::cases()),
            'leaf_size_mm'   => fake()->numberBetween(20, 80),
            'weight_grams'   => fake()->numberBetween(200, 1500),
        ]);
    }

    foreach (range(0, 6) as $w) {
        $wev = CareEvent::factory()->ofType('watering')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subWeeks($w * 2),
        ]);
        WateringDetail::create(['care_event_id' => $wev->id, 'amount_ml' => 200]);
    }

    return $plant;
}

it('shows range controls on each of the five trend charts', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->assertVisible('@health-trend-chart')
            ->assertVisible('@weight-trend-chart')
            ->assertVisible('@growth-trend-chart')
            ->assertVisible('@leaf-size-trend-chart')
            ->assertVisible('@chart-range-7d')
            ->assertVisible('@chart-range-30d')
            ->assertVisible('@chart-range-90d')
            ->assertVisible('@chart-range-all');

        $browser->click('@chart-tab-light')
            ->waitFor('@light-trend-chart')
            ->assertVisible('@light-trend-chart');

        // After switching tabs, the first @chart-range-* DOM match is from
        // the now-hidden trends tab. Scope to the active tab content.
        $browser->assertScript(
            "return document.querySelector('[data-state=\"active\"] [dusk=\"chart-range-7d\"]') !== null",
            true,
        );
        $browser->assertScript(
            "return document.querySelector('[data-state=\"active\"] [dusk=\"chart-range-30d\"]') !== null",
            true,
        );
        $browser->assertScript(
            "return document.querySelector('[data-state=\"active\"] [dusk=\"chart-range-90d\"]') !== null",
            true,
        );
        $browser->assertScript(
            "return document.querySelector('[data-state=\"active\"] [dusk=\"chart-range-all\"]') !== null",
            true,
        );
    });
});

it('reduces plotted SVG elements when switching from All to 7d', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->waitFor('@health-trend-chart')
            ->click('@chart-range-all')
            ->pause(500);

        $allCircles = count($browser->elements('[dusk="health-trend-chart"] svg circle'));

        $browser->click('@chart-range-7d')
            ->pause(500);

        $sevenDayCircles = count($browser->elements('[dusk="health-trend-chart"] svg circle'));

        expect($sevenDayCircles)->toBeLessThan($allCircles);
    });
});

it('restores all data points when switching back to All range', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->waitFor('@health-trend-chart')
            ->pause(500);

        $initialCircles = count($browser->elements('[dusk="health-trend-chart"] svg circle'));

        $browser->click('@chart-range-7d')
            ->pause(500)
            ->click('@chart-range-all')
            ->pause(500);

        $restoredCircles = count($browser->elements('[dusk="health-trend-chart"] svg circle'));

        expect($restoredCircles)->toBe($initialCircles);
    });
});

it('degrades gracefully when a selected range has zero observations', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Old Data Plant']);

    foreach ([40, 60] as $daysAgo) {
        $event = CareEvent::factory()->ofType('observation')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subDays($daysAgo),
        ]);
        Observation::create([
            'care_event_id'  => $event->id,
            'overall_health' => 4,
            'weight_grams'   => 500,
        ]);
    }

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->waitFor('@health-trend-chart')
            ->click('@chart-range-7d')
            ->pause(500)
            ->assertVisible('@charts-panel')
            ->assertVisible('@health-trend-chart');
    });
});

it('shows the chart tab bar and empty state for a plant with no observations', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Empty Chart Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->assertVisible('@chart-tab-trends')
            ->assertVisible('@chart-tab-activity')
            ->assertVisible('@chart-tab-environment')
            ->assertSee('No trend data yet');
    });
});

it('renders environment tab with day/week/month granularity controls', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $sensor = Sensor::create([
        'mac'   => 'AA:BB:CC:DD:EE:FF',
        'name'  => 'Test Sensor',
        'color' => '#FF6B6B',
        'type'  => 'hygrometer',
    ]);
    $sensor->plants()->attach($plant);
    SensorReading::create([
        'sensor_id'   => $sensor->id,
        'data'        => ['temperature' => 22.5, 'humidity' => 65.0],
        'recorded_at' => now()->subHours(2),
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->click('@chart-tab-environment')
            ->waitFor('@env-granularity-day')
            ->assertVisible('@env-granularity-week')
            ->assertVisible('@env-granularity-month')
            ->click('@env-granularity-day')
            ->pause(500)
            ->assertVisible('@environment-chart')
            ->click('@env-granularity-month')
            ->pause(500)
            ->assertVisible('@environment-chart');
    });
});

it('renders the activity heatmap on the activity tab', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->click('@chart-tab-activity')
            ->waitFor('@activity-heatmap')
            ->assertVisible('@activity-heatmap');
    });
});

it('renders timeline overlay with care event markers on the trends tab', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->waitFor('@timeline-overlay')
            ->assertVisible('@timeline-overlay');
    });
});

it('keeps charts legible at mobile landscape viewport', function (): void {
    $user  = User::factory()->create();
    $plant = createRichPlant();

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(812, 375)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@charts-panel')
            ->assertScript("return document.querySelector('[dusk=\"charts-panel\"]').offsetWidth > 0", true);
    });
});
