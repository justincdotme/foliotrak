<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('leaves the weight field empty after backspacing all digits', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Weight Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->type('@weight-lb', '123')
            ->keys('@weight-lb', '{backspace}', '{backspace}', '{backspace}')
            ->assertInputValue('@weight-lb', '');
    });
});

it('does not zero-pad a single digit typed into an empty weight field', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Weight Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->clear('@weight-lb')
            ->type('@weight-lb', '5')
            ->assertInputValue('@weight-lb', '5');
    });
});

it('saves an observation with empty weight fields', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Weight Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Observation');
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->observation->weight_grams)->toBeNull();
});

it('round-trips a lb/oz weight through canonical grams unchanged', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Weight Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->type('@weight-lb', '1')
            ->type('@weight-oz', '4')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->assertInputValue('@weight-lb', '1')
            ->assertInputValue('@weight-oz', '4');
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event->observation->weight_grams)->toBe(567);
});
