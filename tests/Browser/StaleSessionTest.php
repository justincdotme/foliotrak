<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('redirects to login when the session cookie is deleted before form submission', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Session Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '200')
            ->deleteCookie('foliotrak-session')
            ->click('@care-form-submit')
            ->waitForLocation('/login', 15)
            ->assertPathIs('/login');
    });
});

it('recovers after a CSRF token mismatch by reloading the page', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Session Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '200')
            ->deleteCookie('XSRF-TOKEN')
            ->click('@care-form-submit')
            ->pause(3000)
            ->waitFor('@log-watering');
    });
});

it('shows field-specific validation errors instead of session expired', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Session Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->clear('@watering-date')
            ->click('@care-form-submit')
            ->waitForText('Pick a date and time')
            ->assertDontSee('Your session expired');
    });
});
