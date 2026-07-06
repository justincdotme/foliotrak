<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('logs a watering through the log modal and reflects it on the timeline', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Watering Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '250')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Watering')
            ->click('@timeline-item')
            ->waitForText('250 ml');
    });

    $plant->refresh();
    $event = $plant->wateringEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->watering->amount_ml)->toBe(250);
});

it('logs an observation with a seeded symptom and shows it in the timeline detail', function (): void {
    $user    = User::factory()->create();
    $plant   = Plant::factory()->create(['common_name' => 'Dusk Observation Plant']);
    $symptom = Symptom::where('key', 'wilting')->firstOrFail();

    $this->browse(function (Browser $browser) use ($user, $plant, $symptom): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click("@symptom-{$symptom->key}")
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Observation')
            ->click('@timeline-item')
            ->waitForText($symptom->label);
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->observation->symptoms->pluck('id')->all())->toContain($symptom->id);
});

it('edits an existing watering event and persists the change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Edit Plant']);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 100]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->assertInputValue('@watering-amount', '100')
            ->type('@watering-amount', '400')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitForText('400 ml');
    });

    $event->refresh();
    expect($event->watering->amount_ml)->toBe(400);
});

it('deletes an event from the timeline', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Plant']);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 150]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-delete')
            ->click('@timeline-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@timeline-item')
            ->assertSee('No care events logged yet');
    });

    expect(CareEvent::find($event->id))->toBeNull();
    expect(WateringDetail::find($event->id))->toBeNull();
});
