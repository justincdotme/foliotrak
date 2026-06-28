<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('navigates between pages via the desktop top bar', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar');

        $browser->click('@nav-plants')
            ->waitForLocation('/plants')
            ->assertPathIs('/plants');

        $browser->click('@nav-insights')
            ->waitForLocation('/insights')
            ->assertPathIs('/insights');

        $browser->click('@nav-dashboard')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

it('navigates to the dashboard from the logo', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar')
            ->click('@logo-link')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

it('shows add plant and navigates to settings via the user menu', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar')
            ->assertVisible('@add-plant')
            ->click('@user-menu')
            ->waitFor('@settings-link')
            ->assertVisible('@settings-link')
            ->assertVisible('@logout-button')
            ->click('@settings-link')
            ->waitForLocation('/settings')
            ->assertPathIs('/settings');
    });
});

it('navigates between pages via the mobile tab bar', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(375, 812)
            ->visit('/')
            ->waitFor('@app-shell')
            ->waitFor('@tab-bar');

        $browser->click('@tab-plants')
            ->waitForLocation('/plants')
            ->assertPathIs('/plants');

        $browser->click('@tab-insights')
            ->waitForLocation('/insights')
            ->assertPathIs('/insights');

        $browser->click('@tab-more')
            ->waitForLocation('/settings')
            ->assertPathIs('/settings');

        $browser->click('@tab-dashboard')
            ->waitForLocation('/')
            ->assertPathIs('/');
    });
});

it('shows the add plant action in the mobile header', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(375, 812)
            ->visit('/')
            ->waitFor('@app-shell')
            ->waitFor('@mobile-header')
            ->assertVisible('@add-plant');
    });
});

it('toggles the theme and persists the choice across reload', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar');

        $browser->script("localStorage.setItem('foliotrak-theme', 'light')");
        $browser->refresh()
            ->waitFor('@app-shell')
            ->assertScript('document.documentElement.classList.contains("dark")', false);

        $browser->click('@theme-toggle')
            ->pause(300)
            ->assertScript('document.documentElement.classList.contains("dark")', true);

        $browser->refresh()
            ->waitFor('@app-shell')
            ->assertScript('document.documentElement.classList.contains("dark")', true);

        $browser->click('@theme-toggle')
            ->pause(300)
            ->assertScript('document.documentElement.classList.contains("dark")', false);
    });
});

it('renders mobile chrome at phone and tablet portrait viewports', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(375, 812)
            ->visit('/')
            ->waitFor('@app-shell');

        $browser->waitFor('@mobile-header')
            ->assertVisible('@mobile-header')
            ->assertVisible('@tab-bar')
            ->assertMissing('@top-bar');

        $browser->resize(812, 375)
            ->waitFor('@mobile-header')
            ->assertVisible('@mobile-header')
            ->assertVisible('@tab-bar')
            ->assertMissing('@top-bar');

        $browser->resize(768, 1024)
            ->waitFor('@mobile-header')
            ->assertVisible('@mobile-header')
            ->assertVisible('@tab-bar')
            ->assertMissing('@top-bar');
    });
});

it('renders desktop chrome at tablet landscape and desktop viewports', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->logout()
            ->loginAs($user)
            ->resize(1024, 768)
            ->visit('/')
            ->waitFor('@app-shell');

        $browser->waitFor('@top-bar')
            ->assertVisible('@top-bar')
            ->assertMissing('@tab-bar')
            ->assertMissing('@mobile-header');

        $browser->resize(1280, 800)
            ->waitFor('@top-bar')
            ->assertVisible('@top-bar')
            ->assertMissing('@tab-bar')
            ->assertMissing('@mobile-header');
    });
});
