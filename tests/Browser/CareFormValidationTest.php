<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Plant;
use App\Models\RelocationDetail;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('shows a validation error when submitting the watering form with a cleared date', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal');

        $browser->script(
            "var el = document.querySelector('[dusk=\"watering-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '');"
            . "el.dispatchEvent(new Event('input', { bubbles: true }));",
        );

        $browser->click('@care-form-submit')
            ->waitForText('Pick a date and time')
            ->assertPresent('@log-modal');
    });
});

it('shows a validation error when submitting the fertilizing form with a cleared date', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-fertilizing')
            ->click('@log-fertilizing')
            ->waitFor('@log-modal');

        $browser->script(
            "var el = document.querySelector('[dusk=\"fertilizing-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '');"
            . "el.dispatchEvent(new Event('input', { bubbles: true }));",
        );

        $browser->click('@care-form-submit')
            ->waitForText('Pick a date and time')
            ->assertPresent('@log-modal');
    });
});

it('shows a validation error when submitting the repotting form with a cleared date', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-repotting')
            ->click('@log-repotting')
            ->waitFor('@log-modal');

        $browser->script(
            "var el = document.querySelector('[dusk=\"repotting-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '');"
            . "el.dispatchEvent(new Event('input', { bubbles: true }));",
        );

        $browser->click('@care-form-submit')
            ->waitForText('Pick a date and time')
            ->assertPresent('@log-modal');
    });
});

it('blocks submission of the observation form when the date is cleared', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal');

        $browser->script(
            "var el = document.querySelector('[dusk=\"observation-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '');"
            . "el.dispatchEvent(new Event('input', { bubbles: true }));",
        );

        $browser->click('@care-form-submit')
            ->pause(500)
            ->assertPresent('@log-modal');
    });

    expect($plant->observationEvents()->count())->toBe(0);
});

it('shows a validation error when submitting the relocation edit form with a cleared date', function (): void {
    $user     = User::factory()->create();
    $plant    = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);
    $location = Location::factory()->create(['name' => 'Test Room']);
    $event    = CareEvent::factory()->ofType('relocation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    RelocationDetail::create([
        'care_event_id'  => $event->id,
        'to_location_id' => $location->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal');

        $browser->script(
            "var el = document.querySelector('[dusk=\"relocation-date\"]');"
            . "Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '');"
            . "el.dispatchEvent(new Event('input', { bubbles: true }));",
        );

        $browser->click('@relocation-save')
            ->waitForText('Pick a date and time')
            ->assertPresent('@log-modal');
    });
});

it('disables the add plant submit button and shows tooltip when the name is empty', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->assertDisabled('@add-plant-submit')
            ->mouseover('@add-plant-submit')
            ->waitForText('Enter a plant name');
    });
});

it('disables the submit button while a care form is saving', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Validation Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->click('@care-form-submit');

        // The button should be disabled during the async submit window.
        // If the submission completes before we can observe, the modal will
        // already be gone, which also proves the happy path worked.
        $result = $browser->script(
            "var btn = document.querySelector('[dusk=\"care-form-submit\"]');"
            . "return btn ? btn.disabled : 'gone';",
        );

        $browser->waitUntilMissing('@log-modal');

        // Either we caught the disabled state mid-submit or the element was
        // already removed (modal closed before the script ran).
        expect($result[0])->toBeIn([true, 'gone']);
    });
});
