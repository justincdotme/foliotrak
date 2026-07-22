<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('pre-fills the watering form time to approximately the current time', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Defaults Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal');

        $result   = $browser->script("return document.querySelector('[dusk=\"watering-date\"]').value;");
        $formTime = Carbon::parse($result[0]);
        $now      = Carbon::now();

        expect($formTime->diffInMinutes($now))->toBeLessThanOrEqual(5);
    });
});

it('pre-fills the observation form date to today', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Defaults Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal');

        $result = $browser->script("return document.querySelector('[dusk=\"observation-date\"]').value;");
        $today  = now()->format('Y-m-d');

        expect($result[0])->toContain($today);
    });
});

it('pre-fills the fertilizing form date to today', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Defaults Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-fertilizing')
            ->click('@log-fertilizing')
            ->waitFor('@log-modal');

        $result = $browser->script("return document.querySelector('[dusk=\"fertilizing-date\"]').value;");
        $today  = now()->format('Y-m-d');

        expect($result[0])->toContain($today);
    });
});
