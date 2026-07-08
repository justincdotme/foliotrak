<?php

declare(strict_types=1);

use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

/**
 * Non-image file that triggers a server-side validation rejection.
 *
 * @return string
 */
function invalidPhotoFixture(): string
{
    $path = sys_get_temp_dir() . '/dusk-invalid.jpg';

    if (! file_exists($path)) {
        file_put_contents($path, 'This is not a valid image file');
    }

    return $path;
}

it('shows a visible error when a care form submission fails', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Error Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '250');

        // Soft-delete the plant so the API returns 404 on submit.
        $plant->delete();

        $browser->click('@care-form-submit')
            ->waitFor('@form-error')
            ->assertVisible('@form-error');
    });
});

it('dismisses the error after a successful resubmission', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Dismiss Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '250');

        $plant->delete();

        $browser->click('@care-form-submit')
            ->waitFor('@form-error')
            ->assertVisible('@form-error');

        $plant->restore();

        $browser->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->assertMissing('@form-error');
    });
});

it('shows a visible error when a photo upload uses an invalid file', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Photo Error Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', invalidPhotoFixture())
            ->click('@care-form-submit')
            ->waitFor('[role="alert"]')
            ->assertVisible('[role="alert"]');
    });
});

it('does not create a duplicate observation when resubmitting after a photo upload failure', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Retry Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', invalidPhotoFixture())
            ->click('@care-form-submit')
            ->waitFor('[role="alert"]')
            ->click('@care-form-submit')
            ->waitFor('[dusk="care-form-submit"]:not([disabled])');
    });

    expect($plant->observationEvents()->count())->toBe(1);
});
