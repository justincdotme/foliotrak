<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('persists the fertilizing interval override after save and page reload', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Schedule Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Set a schedule')
            ->waitFor('@fertilizing-interval')
            ->type('@fertilizing-interval', '14')
            ->press('Save schedule')
            ->waitUntilMissing('@fertilizing-interval')
            ->refresh()
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Edit schedule')
            ->waitFor('@fertilizing-interval')
            ->assertInputValue('@fertilizing-interval', '14');
    });

    $plant->refresh();
    expect($plant->fertilizing_interval_days_override)->toBe(14);
});

it('shows countdown and progress bar when plant has less than 28 days of history', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Young Plant']);

    foreach (range(0, 3) as $i) {
        $ev = CareEvent::factory()->ofType('watering')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subDays($i * 5),
        ]);
        WateringDetail::create(['care_event_id' => $ev->id, 'amount_ml' => 200]);
    }

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Recommended')
            ->waitFor('@recommended-countdown')
            ->assertVisible('@recommended-countdown')
            ->assertVisible('@recommended-progress')
            ->assertSeeIn('@recommended-countdown', 'remaining to unlock recommendations');
    });
});

it('shows median watering schedule when plant has 28+ days of history', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Mature Plant']);

    foreach (range(0, 6) as $i) {
        $ev = CareEvent::factory()->ofType('watering')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subDays($i * 6),
        ]);
        WateringDetail::create(['care_event_id' => $ev->id, 'amount_ml' => 200]);
    }

    $obsEvent = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(30),
    ]);
    Observation::create([
        'care_event_id'  => $obsEvent->id,
        'overall_health' => 4,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Recommended')
            ->waitFor('@recommended-median')
            ->assertVisible('@recommended-median')
            ->assertSeeIn('@recommended-median', 'Water about every');
    });
});

it('adopts recommended watering interval and switches to my schedule tab', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Mature Plant']);

    foreach (range(0, 6) as $i) {
        $ev = CareEvent::factory()->ofType('watering')->create([
            'plant_id'    => $plant->id,
            'occurred_at' => now()->subDays($i * 6),
        ]);
        WateringDetail::create(['care_event_id' => $ev->id, 'amount_ml' => 200]);
    }

    $obsEvent = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(30),
    ]);
    Observation::create([
        'care_event_id'  => $obsEvent->id,
        'overall_health' => 4,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Recommended')
            ->waitFor('@adopt-schedule')
            ->press('@adopt-schedule')
            ->waitFor('@my-schedule-tab');
    });

    $plant->refresh();
    expect($plant->watering_interval_days_override)->not->toBeNull();
});

it('shows empty state on recommended tab when plant has no watering history', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'No Events Plant']);

    // Observation past the 28-day gate with health data, but no watering events
    $obsEvent = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(35),
    ]);
    Observation::create([
        'care_event_id'  => $obsEvent->id,
        'overall_health' => 4,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->scrollTo('@schedule-section')
            ->press('Recommended')
            ->waitFor('@recommended-empty')
            ->assertVisible('@recommended-empty');
    });
});
