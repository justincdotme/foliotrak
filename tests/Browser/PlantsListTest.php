<?php

declare(strict_types=1);

use App\Models\Location;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->seed(CareLookupSeeder::class);
});

it('renders a plant card with all data points', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['name' => 'Kitchen sill']);
    $tag = Tag::factory()->create(['name' => 'Tropical']);
    $plant = Plant::factory()->create([
        'common_name' => 'Monstera',
        'scientific_name' => 'Monstera deliciosa',
        'nickname' => 'Big M',
        'location_id' => $location->id,
    ]);
    $plant->tags()->attach($tag->id);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitFor('@plant-card')
            ->assertSee('Monstera')
            ->assertSee('Monstera deliciosa')
            ->assertSee('Big M')
            ->assertSee('Kitchen sill')
            ->assertSee('Tropical')
            ->assertVisible('@condition-chip')
            ->assertSeeIn('@condition-chip', 'No reading')
            ->assertVisible('@water-drop')
            ->assertSee('No watering logged');
    });
});

it('filters plants by tag', function () {
    $user = User::factory()->create();
    $tagA = Tag::factory()->create(['name' => 'Succulent']);
    $tagB = Tag::factory()->create(['name' => 'Trailing']);
    $plantA = Plant::factory()->create(['common_name' => 'Aloe']);
    $plantB = Plant::factory()->create(['common_name' => 'Pothos']);
    $plantA->tags()->attach($tagA->id);
    $plantB->tags()->attach($tagB->id);

    $this->browse(function (Browser $browser) use ($user, $tagA) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitForTextIn('@plants-count', '2')
            ->assertSee('Aloe')
            ->assertSee('Pothos');

        $browser->select('@plants-tag-filter', (string) $tagA->id)
            ->waitForTextIn('@plants-count', '1')
            ->assertSee('Aloe')
            ->assertDontSee('Pothos');

        $browser->select('@plants-tag-filter', '')
            ->waitForTextIn('@plants-count', '2')
            ->assertSee('Aloe')
            ->assertSee('Pothos');
    });
});

it('toggles status filter chips', function () {
    $user = User::factory()->create();
    Plant::factory()->create(['common_name' => 'Green Fern', 'status' => 'active']);
    Plant::factory()->create(['common_name' => 'Old Rose', 'status' => 'archived']);
    Plant::factory()->create(['common_name' => 'Gone Cactus', 'status' => 'dead']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitForTextIn('@plants-count', '1');

        $browser->assertSee('Green Fern')
            ->assertDontSee('Old Rose')
            ->assertDontSee('Gone Cactus');

        $browser->click('@status-chip-archived')
            ->waitForTextIn('@plants-count', '2')
            ->assertSee('Green Fern')
            ->assertSee('Old Rose')
            ->assertDontSee('Gone Cactus');

        $browser->click('@status-chip-active')
            ->waitForTextIn('@plants-count', '1')
            ->assertDontSee('Green Fern')
            ->assertSee('Old Rose')
            ->assertDontSee('Gone Cactus');
    });
});

it('narrows plants by name search', function () {
    $user = User::factory()->create();
    Plant::factory()->create([
        'common_name' => 'Pothos',
        'scientific_name' => 'Epipremnum aureum',
    ]);
    Plant::factory()->create([
        'common_name' => 'Snake plant',
        'scientific_name' => 'Dracaena trifasciata',
    ]);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitForTextIn('@plants-count', '2');

        $browser->type('@plants-search', 'Pothos')
            ->waitForTextIn('@plants-count', '1')
            ->assertSee('Pothos')
            ->assertDontSee('Snake plant');

        $browser->type('@plants-search', 'Dracaena')
            ->waitForTextIn('@plants-count', '1')
            ->assertSee('Snake plant')
            ->assertDontSee('Pothos');
    });
});

it('shows empty state when no plants exist', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitFor('@plants-empty')
            ->assertSee('No plants match');
    });
});

it('shows empty state when search matches nothing', function () {
    $user = User::factory()->create();
    Plant::factory()->create(['common_name' => 'Pothos']);

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitForTextIn('@plants-count', '1')
            ->type('@plants-search', 'zzzznotaplant')
            ->waitFor('@plants-empty')
            ->assertSee('No plants match');
    });
});

it('navigates to plant detail on card click', function () {
    $user = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Monstera']);

    $this->browse(function (Browser $browser) use ($user, $plant) {
        $browser->loginAs($user)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitFor('@plant-card')
            ->click('@plant-card')
            ->waitForLocation('/plants/'.$plant->id)
            ->assertPathIs('/plants/'.$plant->id);
    });
});
