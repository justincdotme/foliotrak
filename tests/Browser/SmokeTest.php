<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('renders the login form', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->waitFor('@login-form')
            ->assertSee('Welcome back')
            ->assertSee('Sign in');
    });
});

it('renders the authenticated shell after login', function () {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user) {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@app-shell')
            ->assertSee('Dashboard');
    });
});
