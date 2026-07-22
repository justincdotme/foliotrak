<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('hides a plant from the active list after changing its status to dead', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Cache Test Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->within('@edit-status', fn (Browser $b) => $b->press('Dead'))
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->click('@nav-plants')
            ->waitForLocation('/plants')
            ->waitFor('@plants-empty')
            ->assertDontSee('Cache Test Plant');
    });
});

it('shows an archived plant under the archived filter after status change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Cache Test Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->within('@edit-status', fn (Browser $b) => $b->press('Archived'))
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal')
            ->click('@nav-plants')
            ->waitForLocation('/plants')
            ->waitFor('@plants-empty')
            ->assertDontSee('Cache Test Plant')
            ->click('@status-chip-archived')
            ->waitFor('@plant-card')
            ->assertSee('Cache Test Plant');
    });
});

it('reflects a renamed tag on plant cards after navigating to the plants page', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Cache Test Plant']);
    $tag   = Tag::factory()->create(['name' => 'OldTagName']);
    $plant->tags()->attach($tag);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@app-shell')
            ->waitFor('@tag-item')
            ->click('[aria-label="Rename OldTagName"]')
            ->waitFor('@tag-rename')
            ->type('@tag-rename', 'NewTagName')
            ->keys('@tag-rename', '{enter}')
            ->waitUntilMissing('@tag-rename')
            ->click('@nav-plants')
            ->waitForLocation('/plants')
            ->waitFor('@plant-card')
            ->assertSee('NewTagName');
    });
});

it('removes a deleted tag from plant cards after navigating to the plants page', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Cache Test Plant']);
    $tag   = Tag::factory()->create(['name' => 'OldTagName']);
    $plant->tags()->attach($tag);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@app-shell')
            ->waitFor('@tag-item')
            ->click('@tag-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@tag-item')
            ->click('@nav-plants')
            ->waitForLocation('/plants')
            ->waitFor('@plant-card')
            ->assertDontSee('OldTagName');
    });
});

it('updates the watering label on the plant card after logging a watering', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Cache Test Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '250')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->click('@nav-plants')
            ->waitForLocation('/plants')
            ->waitFor('@plant-card')
            ->assertSee('Watered today')
            ->assertDontSee('No watering logged');
    });
});
