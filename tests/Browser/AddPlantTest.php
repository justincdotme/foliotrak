<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\SpeciesCache;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

/**
 * Seeds two Monstera species into the local cache so the collection-driver
 * typeahead returns results without hitting GBIF.
 *
 * @return void
 */
function seedSpeciesCache(): void
{
    SpeciesCache::factory()->create([
        'gbif_key'        => '12345',
        'scientific_name' => 'Monstera deliciosa',
        'canonical_name'  => 'Monstera deliciosa',
        'common_name'     => 'Swiss Cheese Plant',
        'rank'            => 'SPECIES',
        'family'          => 'Araceae',
        'cached_at'       => now(),
    ]);
    SpeciesCache::factory()->create([
        'gbif_key'        => '67890',
        'scientific_name' => 'Monstera adansonii',
        'canonical_name'  => 'Monstera adansonii',
        'common_name'     => 'Monkey Mask',
        'rank'            => 'SPECIES',
        'family'          => 'Araceae',
        'cached_at'       => now(),
    ]);
}

it('disables the submit button and shows a tooltip when the name is empty', function (): void {
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

it('creates a plant when a name is entered and submitted', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Dusk Test Plant')
            ->assertEnabled('@add-plant-submit')
            ->click('@add-plant-submit')
            ->waitUntilMissing('@add-plant-modal');
    });

    expect(Plant::where('common_name', 'Dusk Test Plant')->exists())->toBeTrue();
});

it('shows species suggestions when typing three or more characters', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Mon')
            ->waitFor('@species-suggestion')
            ->assertSeeIn('@species-suggestions', 'Monstera deliciosa')
            ->assertSeeIn('@species-suggestions', 'Monstera adansonii');
    });
});

it('fills the scientific name and stores the GBIF key when a species is selected', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Mon')
            ->waitFor('@species-suggestion');

        // Target deliciosa explicitly; suggestion order is not guaranteed.
        $browser->driver->findElement(
            WebDriverBy::xpath("//*[@dusk='species-suggestion'][contains(., 'deliciosa')]"),
        )->click();

        $browser->waitUntilMissing('@species-suggestions')
            ->assertInputValue('@add-plant-name', 'Swiss Cheese Plant')
            ->waitForText('Matched GBIF')
            ->click('@add-plant-submit')
            ->waitUntilMissing('@add-plant-modal');
    });

    $plant = Plant::where('common_name', 'Swiss Cheese Plant')->firstOrFail();
    expect($plant->scientific_name)->toBe('Monstera deliciosa');
    expect($plant->gbif_key)->toBe('12345');
});

it('saves a plant with a freeform name when keep-as-custom-name is clicked', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'My Unique Cactus')
            ->waitFor('@species-custom')
            ->click('@species-custom')
            ->waitUntilMissing('@species-suggestions')
            ->click('@add-plant-submit')
            ->waitUntilMissing('@add-plant-modal');
    });

    $plant = Plant::where('common_name', 'My Unique Cactus')->firstOrFail();
    expect($plant->gbif_key)->toBeNull();
});

it('dismisses the typeahead dropdown on outside click', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Mon')
            ->waitFor('@species-suggestion')
            ->assertPresent('@species-suggestions');

        // Click the modal title area, which is above and outside the popover.
        $browser->driver->findElement(
            WebDriverBy::xpath("//*[@dusk='add-plant-modal']//h2"),
        )->click();

        $browser->waitUntilMissing('@species-suggestions');
    });
});

it('does not show suggestions when fewer than three characters are typed', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Mo')
            ->pause(500)
            ->assertMissing('@species-suggestion');
    });
});

it('navigates suggestions with arrow keys, selects with enter, and closes with escape', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Mon')
            ->waitFor('@species-suggestion');

        // Arrow down moves past the first auto-selected item to the second.
        // JS-dispatched keyboard events are more reliable through the cmdk
        // portal than Selenium's sendKeys.
        $browser->script(
            "var el = document.querySelector('[dusk=\"add-plant-name\"]');"
            . "el.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', code: 'ArrowDown', keyCode: 40, bubbles: true }));",
        );
        $browser->pause(200);
        $browser->script(
            "var el = document.querySelector('[dusk=\"add-plant-name\"]');"
            . "el.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', code: 'Enter', keyCode: 13, bubbles: true }));",
        );

        // The second item depends on sort order; verify the popover closed
        // and a species was selected (name field is no longer "Mon").
        $browser->waitUntilMissing('@species-suggestions');
        $selected = $browser->inputValue('@add-plant-name');
        expect($selected)->not->toBe('Mon');
        expect(in_array($selected, ['Swiss Cheese Plant', 'Monkey Mask'], true))->toBeTrue();

        // Re-open and test Escape
        $browser->type('@add-plant-name', 'Mon')
            ->waitFor('@species-suggestion');

        $browser->script(
            "var el = document.querySelector('[dusk=\"add-plant-name\"]');"
            . "el.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true }));",
        );

        $browser->waitUntilMissing('@species-suggestions');
    });
});

it('shows the placeholder before typing and a no-results message for unmatched queries', function (): void {
    $user = User::factory()->create();
    seedSpeciesCache();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->pause(400)
            ->assertAttribute('@add-plant-name', 'placeholder', "Pothos, Monstera, snake plant\u{2026}")
            ->assertMissing('@species-suggestions')
            ->type('@add-plant-name', 'Zzzznotaplant')
            ->waitFor('@species-suggestions')
            ->waitForText('No matches', 10);
    });
});

it('opens the add plant modal from the plants page button', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@plants-count');

        // Use JS click for the in-page button; raw WebDriver click can miss
        // the React handler when the sticky header partially overlaps.
        $browser->script(
            "var btns = document.querySelectorAll('main button');"
            . 'for (var i = 0; i < btns.length; i++) {'
            . "  if (btns[i].textContent.includes('Add plant')) {"
            . '    btns[i].click(); break;'
            . '  }'
            . '}',
        );

        $browser->waitFor('@add-plant-modal');
    });
});
