<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Laravel\Dusk\Browser;

it('shows delete icon next to edit icon on plant detail header', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Target']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@delete-plant')
            ->assertPresent('@edit-plant')
            ->assertPresent('@delete-plant');
    });
});

it('opens confirm dialog with plant-specific warning when trash icon is clicked', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Target']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@delete-plant')
            ->click('@delete-plant')
            ->waitFor('@confirm-delete')
            ->assertSee('Dusk Delete Target')
            ->assertPresent('@cancel-delete')
            ->assertPresent('@confirm-delete');
    });
});

it('closes dialog and does not delete plant when cancel is clicked', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Target']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@delete-plant')
            ->click('@delete-plant')
            ->waitFor('@confirm-delete')
            ->click('@cancel-delete')
            ->waitUntilMissing('@confirm-delete')
            ->refresh()
            ->waitForText('Dusk Delete Target');
    });

    expect(Plant::withTrashed()->find($plant->id)?->trashed())->toBeFalse();
});

it('deletes plant and navigates to plant list when confirm is clicked', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Target']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@delete-plant')
            ->click('@delete-plant')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitForLocation('/plants')
            ->assertPathIs('/plants')
            ->assertDontSee('Dusk Delete Target');
    });

    expect(Plant::withTrashed()->find($plant->id)?->trashed())->toBeTrue();
});
