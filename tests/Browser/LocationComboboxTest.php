<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('narrows the displayed options when typing in the location combobox', function (): void {
    $user = User::factory()->create();
    $loc1 = Location::factory()->create(['name' => 'Kitchen']);
    Location::factory()->create(['name' => 'Office']);
    Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Location Plant',
        'location_id' => $loc1->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->click('@location-combobox input')
            ->type('@location-combobox input', 'Kit')
            ->waitForText('Kitchen')
            ->assertSee('Kitchen')
            ->assertDontSee('Office')
            ->assertDontSee('Bedroom');
    });
});

it('navigates options with arrow keys selects with enter and closes with escape', function (): void {
    $user = User::factory()->create();
    $loc1 = Location::factory()->create(['name' => 'Kitchen']);
    Location::factory()->create(['name' => 'Office']);
    Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Location Plant',
        'location_id' => $loc1->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal');

        $browser->script("document.querySelector('[dusk=\"location-combobox\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300)
            ->click('@location-combobox input')
            ->pause(200)
            ->keys('@location-combobox input', ['{control}', 'a'], '{backspace}')
            ->waitForText('Bedroom')
            ->keys('@location-combobox input', '{arrow_down}', '{arrow_down}', '{enter}')
            ->pause(300);

        $selected = $browser->script("return document.querySelector('[dusk=\"location-combobox\"] input')?.value")[0];
        expect($selected)->toBe('Office');

        $browser->click('@location-combobox input')
            ->pause(500)
            ->keys('@location-combobox input', '{escape}')
            ->pause(500);
    });
});

it('creates a new location when selecting the create option', function (): void {
    $user = User::factory()->create();
    $loc1 = Location::factory()->create(['name' => 'Kitchen']);
    Location::factory()->create(['name' => 'Office']);
    Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Location Plant',
        'location_id' => $loc1->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal');

        $browser->script("document.querySelector('[dusk=\"location-combobox\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300)
            ->click('@location-combobox input')
            ->pause(200)
            ->keys('@location-combobox input', ['{control}', 'a'], '{backspace}')
            ->pause(100)
            ->keys('@location-combobox input', 'P', 'a', 't', 'i', 'o')
            ->waitForText('Create')
            ->keys('@location-combobox input', '{enter}')
            ->pause(2000);

        $browser->script("document.querySelector('[dusk=\"edit-plant-save\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(500);

        $browser->script("document.querySelector('[dusk=\"edit-plant-modal\"]')?.scrollTo(0, 9999)");
        $browser->pause(500)
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal');
    });

    expect(Location::where('name', 'Patio')->exists())->toBeTrue();
    $plant->refresh();
    expect($plant->location->name)->toBe('Patio');
});

it('closes the combobox when clicking outside without making a selection', function (): void {
    $user = User::factory()->create();
    $loc1 = Location::factory()->create(['name' => 'Kitchen']);
    Location::factory()->create(['name' => 'Office']);
    Location::factory()->create(['name' => 'Bedroom']);
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Location Plant',
        'location_id' => $loc1->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->click('@location-combobox input')
            ->waitFor('[cmdk-list]')
            ->click('@edit-notes')
            ->waitUntilMissing('[cmdk-list]')
            ->assertInputValue('@location-combobox input', 'Kitchen');
    });
});
