<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Laravel\Dusk\Browser;

it('filters the tag list when typing in the tag combobox', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Tag Plant']);
    Tag::factory()->create(['name' => 'Succulent']);
    Tag::factory()->create(['name' => 'Tropical']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->click('@tag-combobox button')
            ->waitFor('@tag-combobox input')
            ->type('@tag-combobox input', 'Succ')
            ->waitForText('Succulent')
            ->assertSee('Succulent')
            ->assertDontSee('Tropical');
    });
});

it('creates a new tag and associates it with the plant', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Tag Plant']);
    Tag::factory()->create(['name' => 'Succulent']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->click('@tag-combobox button')
            ->waitFor('@tag-combobox input')
            ->type('@tag-combobox input', 'NewTag')
            ->waitForText('Create "NewTag"')
            ->keys('@tag-combobox input', '{enter}')
            ->waitUntilMissing('@tag-combobox input')
            ->assertSee('NewTag')
            ->click('@edit-plant-save')
            ->waitUntilMissing('@edit-plant-modal');
    });

    $plant->refresh();
    expect($plant->tags->pluck('name')->all())->toContain('NewTag');
});

it('closes the combobox when clicking outside without making a selection', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Tag Plant']);
    Tag::factory()->create(['name' => 'Succulent']);
    Tag::factory()->create(['name' => 'Tropical']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@edit-plant')
            ->click('@edit-plant')
            ->waitFor('@edit-plant-modal')
            ->click('@tag-combobox button')
            ->waitFor('@tag-combobox input')
            ->type('@tag-combobox input', 'Succ')
            ->waitForText('Succulent')
            ->click('@edit-common-name')
            ->pause(500)
            ->assertDontSee('Succulent');
    });

    $plant->refresh();
    expect($plant->tags->pluck('name')->all())->toBeEmpty();
});
