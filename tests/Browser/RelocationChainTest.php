<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Location;
use App\Models\Plant;
use App\Models\RelocationDetail;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('reverts the header location when the latest relocation is deleted', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locB->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $ev2 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(15),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev2->id,
        'from_location_id' => $locA->id,
        'to_location_id'   => $locB->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Office')
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-delete')
            ->click('@timeline-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@confirm-delete')
            ->waitForTextIn('@plant-location', 'Kitchen')
            ->assertSeeIn('@plant-location', 'Kitchen');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locA->id);
});

it('keeps the header location when a non-latest relocation is deleted', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locB->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $ev2 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(15),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev2->id,
        'from_location_id' => $locA->id,
        'to_location_id'   => $locB->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Office')
            ->waitFor('@timeline-item');

        // Expand the second (older) timeline item
        $browser->script("document.querySelectorAll('[dusk=\"timeline-item\"]')[1].click();");

        $browser->waitFor('@timeline-delete')
            ->click('@timeline-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@confirm-delete');

        $browser->waitUntil('document.querySelectorAll("[dusk=timeline-item]").length === 1')
            ->assertSeeIn('@plant-location', 'Office');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locB->id);
});

it('shows no location when the only relocation is deleted', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locA->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Kitchen')
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-delete')
            ->click('@timeline-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@confirm-delete')
            ->waitUntilMissing('@timeline-item')
            ->waitForTextIn('@plant-location', 'No location');
    });

    $plant->refresh();
    expect($plant->location_id)->toBeNull();
});

it('keeps the header at the latest location after a backdated relocation', function (): void {
    $user = User::factory()->create();
    $locA = Location::factory()->create(['name' => 'Kitchen']);
    $locB = Location::factory()->create(['name' => 'Office']);
    Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locB->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $ev2 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(15),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev2->id,
        'from_location_id' => $locA->id,
        'to_location_id'   => $locB->id,
    ]);

    $backdated = now()->subDays(45)->format('Y-m-d\TH:i');

    $this->browse(function (Browser $browser) use ($user, $plant, $backdated): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Office');

        // Change location to Bedroom via edit plant modal (creates relocation at now)
        $browser->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->pause(300);

        $browser->script("document.querySelector('[dusk=\"location-combobox\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300)
            ->click('@location-combobox input')
            ->pause(200)
            ->keys('@location-combobox input', ['{control}', 'a'], '{backspace}')
            ->pause(100)
            ->keys('@location-combobox input', 'B', 'e', 'd', 'r', 'o', 'o', 'm')
            ->waitForText('Bedroom')
            ->keys('@location-combobox input', '{enter}')
            ->pause(300)
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->waitForTextIn('@plant-location', 'Bedroom');

        // Wait for the new relocation to appear in timeline
        $browser->waitUntil('document.querySelectorAll("[dusk=timeline-item]").length >= 3');

        // Expand the first (newest) timeline item and edit its date to 45 days ago
        $browser->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('@relocation-date');

        $browser->script(
            "var el = document.querySelector('[dusk=\"relocation-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '{$backdated}');"
            . "el.dispatchEvent(new Event('change', {bubbles: true}));",
        );

        $browser->click('@relocation-save')
            ->waitUntilMissing('@log-modal')
            ->waitForTextIn('@plant-location', 'Office')
            ->assertSeeIn('@plant-location', 'Office');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locB->id);
});

it('flips the header when a relocation date is edited to today', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locB->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $ev2 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(15),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev2->id,
        'from_location_id' => $locA->id,
        'to_location_id'   => $locB->id,
    ]);

    $today = now()->format('Y-m-d\TH:i');

    $this->browse(function (Browser $browser) use ($user, $plant, $today): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Office')
            ->waitFor('@timeline-item');

        // Expand the second (older) timeline item: null->Kitchen
        $browser->script("document.querySelectorAll('[dusk=\"timeline-item\"]')[1].click();");

        $browser->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('@relocation-date');

        // Change date to today so this relocation becomes the latest
        $browser->script(
            "var el = document.querySelector('[dusk=\"relocation-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '{$today}');"
            . "el.dispatchEvent(new Event('change', {bubbles: true}));",
        );

        $browser->click('@relocation-save')
            ->waitUntilMissing('@log-modal')
            ->waitForTextIn('@plant-location', 'Kitchen')
            ->assertSeeIn('@plant-location', 'Kitchen');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locA->id);
});

it('updates the header when the latest relocation destination changes', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $locC  = Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locB->id,
    ]);

    $relocationTypeId = CareEventType::where('key', 'relocation')->first()->id;

    $ev1 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(30),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev1->id,
        'from_location_id' => null,
        'to_location_id'   => $locA->id,
    ]);

    $ev2 = CareEvent::create([
        'plant_id'           => $plant->id,
        'care_event_type_id' => $relocationTypeId,
        'occurred_at'        => now()->subDays(15),
    ]);
    RelocationDetail::create([
        'care_event_id'    => $ev2->id,
        'from_location_id' => $locA->id,
        'to_location_id'   => $locB->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Office')
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('@relocation-destination');

        // Change destination from Office to Bedroom via the combobox
        $browser->keys('@relocation-destination', ['{control}', 'a'], '{backspace}')
            ->pause(100)
            ->keys('@relocation-destination', 'B', 'e', 'd')
            ->waitForText('Bedroom')
            ->keys('@relocation-destination', '{enter}')
            ->pause(500);

        $browser->script("document.querySelector('[dusk=\"relocation-save\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300);
        $browser->script("document.querySelector('[dusk=\"relocation-save\"]:not([disabled])')?.click()");
        $browser->waitUntilMissing('@log-modal')
            ->waitForTextIn('@plant-location', 'Bedroom')
            ->assertSeeIn('@plant-location', 'Bedroom');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locC->id);
});

it('creates a relocation event and updates header when location changes via edit modal', function (): void {
    $user  = User::factory()->create();
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $plant = Plant::factory()->create([
        'common_name' => 'Nomad Plant',
        'location_id' => $locA->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-location')
            ->assertSeeIn('@plant-location', 'Kitchen')
            ->assertSee('No care events logged yet');

        // Open edit modal and change location
        $browser->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->pause(300);

        $browser->script("document.querySelector('[dusk=\"location-combobox\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300)
            ->click('@location-combobox input')
            ->pause(200)
            ->keys('@location-combobox input', ['{control}', 'a'], '{backspace}')
            ->pause(100)
            ->keys('@location-combobox input', 'O', 'f', 'f', 'i', 'c', 'e')
            ->waitForText('Office')
            ->keys('@location-combobox input', '{enter}')
            ->pause(300)
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->waitForTextIn('@plant-location', 'Office')
            ->assertSeeIn('@plant-location', 'Office')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Moved');
    });

    $plant->refresh();
    expect($plant->location_id)->toBe($locB->id);
});
