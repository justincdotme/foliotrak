<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('logs in with valid credentials and lands on the dashboard', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->visit('/login')
            ->waitFor('@login-form')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('Sign in')
            ->waitFor('@app-shell')
            ->assertPathIs('/')
            ->assertAuthenticated();
    });
});

it('shows an error and stays on login with invalid credentials', function (): void {
    User::factory()->create([
        'email'    => 'real@example.com',
        'password' => bcrypt('correct-password'),
    ]);

    $this->browse(function (Browser $browser): void {
        $browser->logout()
            ->visit('/login')
            ->waitFor('@login-form')
            ->type('email', 'real@example.com')
            ->type('password', 'wrong-password')
            ->press('Sign in')
            ->waitFor('@auth-error')
            ->assertVisible('@auth-error')
            ->assertPathIs('/login')
            ->assertGuest();
    });
});

it('logs out from the user menu and returns to login', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->visit('/')
            ->waitFor('@app-shell')
            ->click('@user-menu')
            ->waitFor('@logout-button')
            ->click('@logout-button')
            ->waitForLocation('/login')
            ->assertPathIs('/login')
            ->assertGuest();
    });
});

it('redirects unauthenticated visitors to login', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->logout()
            ->visit('/plants')
            ->waitForLocation('/login')
            ->assertPathIs('/login')
            ->assertGuest();
    });
});

it('persists the session across a full page reload', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->visit('/')
            ->waitFor('@app-shell')
            ->refresh()
            ->waitFor('@app-shell')
            ->assertAuthenticated();
    });
});

it('shows validation messages when submitting with empty fields', function (): void {
    $this->browse(function (Browser $browser): void {
        $browser->logout()
            ->visit('/login')
            ->waitFor('@login-form')
            ->press('Sign in')
            ->waitForText('Enter a valid email')
            ->assertSee('Enter your password');
    });
});

it('disables the submit button during login submission', function (): void {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->visit('/login')
            ->waitFor('@login-form')
            ->type('email', $user->email)
            ->type('password', 'password')
            ->press('Sign in');

        $isDisabled = $browser->script('return document.querySelector("button[type=submit]").disabled')[0];
        expect($isDisabled)->toBeTrue();
    });
});
