<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('persists Pushover user key across page reload', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@pushover-key')
            ->type('@pushover-key', 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG')
            ->click('@pushover-save')
            ->pause(1000)
            ->refresh()
            ->waitFor('@pushover-key')
            ->assertInputValue('@pushover-key', 'uQiRzpo4DXghDmr9QzzfQu27cmVRsG');
    });
});

it('persists system theme preference across page reload', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@theme-system')
            ->click('@theme-system')
            ->pause(500)
            ->refresh()
            ->waitFor('@settings-theme');

        $isSystemSelected = $browser->script(
            "return localStorage.getItem('foliotrak-theme') === 'system'",
        )[0];

        expect($isSystemSelected)->toBeTrue();
    });
});

it('displays the user name and email in the account section', function (): void {
    $user = User::factory()->create([
        'name'  => 'Test User',
        'email' => 'testuser@example.com',
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@account-name')
            ->assertSeeIn('@account-name', 'Test User')
            ->assertSeeIn('@account-email', 'testuser@example.com');
    });
});
