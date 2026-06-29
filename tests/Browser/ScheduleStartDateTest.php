<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function () {
    $this->seed(CareLookupSeeder::class);
});

it('shows the start date picker in the schedule form and persists the value', function () {
    $user = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Test Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant) {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@schedule-section')
            ->press('Set a schedule')
            ->waitFor('@schedule-start-date')
            ->screenshot('01-schedule-edit-form')
            ->assertVisible('@schedule-start-date')
            ->script("var el = document.querySelector('[dusk=\"schedule-start-date\"]'); Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(el, '2026-07-01'); el.dispatchEvent(new Event('change', {bubbles: true}));");

        $browser->type('[placeholder="e.g. 5"]', '7')
            ->screenshot('02-schedule-filled')
            ->press('Save schedule')
            ->waitUntilMissing('@schedule-start-date')
            ->waitFor('@schedule-start-display')
            ->screenshot('03-schedule-saved')
            ->assertSeeIn('@schedule-start-display', 'Jul 1, 2026');
    });

    $plant->refresh();
    expect($plant->watering_schedule_start_date->format('Y-m-d'))->toBe('2026-07-01');
    expect($plant->watering_interval_days_override)->toBe(7);
});
