<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Laravel\Dusk\Browser;

it('renders the plant silhouette visible in dark mode', function (): void {
    $user = User::factory()->create();
    Plant::factory()->create([
        'common_name'    => 'Dusk Dark Mode Plant',
        'cover_photo_id' => null,
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800);

        $browser->script("localStorage.setItem('foliotrak-theme', 'dark')");
        $browser->visit('/plants')
            ->waitFor('@app-shell')
            ->assertScript('document.documentElement.classList.contains("dark")', true);

        $browser->waitFor('@plant-card');

        $opacity = $browser->script(
            "return getComputedStyle(document.querySelector('[dusk=\"plant-card\"] img')).opacity",
        );
        expect((float) $opacity[0])->toBeGreaterThan(0);
    });
});

it('shows cursor pointer on plant card hover', function (): void {
    $user = User::factory()->create();
    Plant::factory()->create(['common_name' => 'Cursor Test Plant']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/plants')
            ->waitFor('@app-shell')
            ->waitFor('@plant-card');

        $cursor = $browser->script(
            "return getComputedStyle(document.querySelector('[dusk=\"plant-card\"]')).cursor",
        );
        expect($cursor[0])->toBe('pointer');
    });
});

it('renders plant card text legible in dark mode', function (): void {
    $user = User::factory()->create();
    Plant::factory()->create(['common_name' => 'Text Legibility Plant']);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800);

        $browser->script("localStorage.setItem('foliotrak-theme', 'dark')");
        $browser->visit('/plants')
            ->waitFor('@app-shell')
            ->assertScript('document.documentElement.classList.contains("dark")', true);

        $browser->waitFor('@plant-card');

        $color = $browser->script(
            "return getComputedStyle(document.querySelector('[dusk=\"plant-card\"] .font-medium')).color",
        );

        preg_match('/rgb\((\d+),\s*(\d+),\s*(\d+)\)/', $color[0], $matches);
        $r = (int) $matches[1];
        $g = (int) $matches[2];
        $b = (int) $matches[3];

        expect($r)->toBeGreaterThan(128);
        expect($g)->toBeGreaterThan(128);
        expect($b)->toBeGreaterThan(128);
    });
});
