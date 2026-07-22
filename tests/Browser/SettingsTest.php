<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Dusk\Browser;

it('shows a tooltip on each disabled action button', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@app-shell');

        // Open the tag inline form; the Add button is disabled while the
        // name field is empty and shows a Radix tooltip on hover.
        $browser->click('@tag-add')
            ->waitFor('@tag-submit')
            ->mouseover('@tag-submit')
            ->waitForText('Enter a tag name');
    });
});

it('suppresses transitions immediately after toggling the theme', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar');

        // The class is added and removed within a single animation frame,
        // so a MutationObserver captures it before rAF fires.
        $browser->script([
            'window.__noTransitionSeen = false;' .
            'new MutationObserver(function() {' .
            "  if (document.documentElement.classList.contains('no-transitions')) {" .
            '    window.__noTransitionSeen = true;' .
            '  }' .
            "}).observe(document.documentElement, {attributes: true, attributeFilter: ['class']});",
        ]);

        $browser->click('@theme-toggle');
        $browser->pause(100);

        $browser->assertScript(
            'return window.__noTransitionSeen === true',
            true,
        );
    });
});

it('removes the transition suppression class after one frame', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/settings')
            ->waitFor('@app-shell')
            ->waitFor('@top-bar');

        $browser->click('@theme-toggle');
        $browser->pause(100);

        $browser->assertScript(
            "return !document.documentElement.classList.contains('no-transitions')",
            true,
        );
    });
});
