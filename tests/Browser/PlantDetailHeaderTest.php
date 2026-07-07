<?php

declare(strict_types=1);

use App\Enums\PlantStatus;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('shows the cover photo, names, condition chip, and tags in the header', function (): void {
    $user  = User::factory()->create();
    $tag   = Tag::factory()->create(['name' => 'Tropical']);
    $plant = Plant::factory()->create([
        'common_name'     => 'Dusk Header Plant',
        'scientific_name' => 'Testus plantus',
        'gbif_key'        => 12345,
        'acquired_on'     => '2024-06-15',
        'notes'           => 'Original notes',
    ]);
    $plant->tags()->attach($tag);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-common-name')
            ->assertPresent('@cover-hero')
            ->assertSeeIn('@plant-common-name', 'Dusk Header Plant')
            ->assertSeeIn('@plant-scientific-name', 'Testus plantus')
            ->assertSee('No reading')
            ->assertSee('Tropical');
    });
});

it('shows the acquired-on date when set', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Date Plant',
        'acquired_on' => '2024-06-15',
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-acquired-date')
            ->assertVisible('@plant-acquired-date')
            ->assertSeeIn('@plant-acquired-date', 'Since');
    });
});

it('shows a GBIF link when gbif_key is set', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk GBIF Plant',
        'gbif_key'    => 12345,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@plant-gbif-link')
            ->assertVisible('@plant-gbif-link')
            ->assertSeeIn('@plant-gbif-link', 'GBIF');
    });
});

it('edits the nickname and notes through the edit modal', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name' => 'Dusk Edit Plant',
        'notes'       => 'Original notes',
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->clear('@edit-common-name')
            ->type('@edit-common-name', 'Updated Name')
            ->clear('@edit-notes')
            ->type('@edit-notes', 'Updated notes')
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->assertSee('Updated Name');
    });

    $plant->refresh();
    expect($plant->nickname)->toBe('Updated Name');
    expect($plant->notes)->toBe('Updated notes');
});

it('archives a plant and it moves to the archived filter', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Archive Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->within('@edit-status', fn (Browser $b) => $b->press('Archived'))
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->visit('/plants')
            ->waitFor('@plants-empty')
            ->assertDontSee('Dusk Archive Plant')
            ->click('@status-chip-archived')
            ->waitFor('@plant-card')
            ->assertSee('Dusk Archive Plant');
    });

    $plant->refresh();
    expect($plant->status)->toBe(PlantStatus::Archived);
});
