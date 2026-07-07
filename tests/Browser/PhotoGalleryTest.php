<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Plant;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

/**
 * @return string
 */
function galleryFixture(): string
{
    $path = sys_get_temp_dir() . '/dusk-gallery.jpg';

    if (! file_exists($path)) {
        $img = imagecreatetruecolor(800, 600);
        imagefilledrectangle($img, 0, 0, 800, 600, (int) imagecolorallocate($img, 50, 150, 200));
        imagejpeg($img, $path, 85);
        imagedestroy($img);
    }

    return $path;
}

/**
 * @param Browser $browser
 *
 * @return void
 */
function completeCropWorkflow(Browser $browser): void
{
    $browser->waitForText('Crop hero photo (2:3)')
        ->waitUsing(10, 100, fn () => count($browser->driver->findElements(
            WebDriverBy::xpath("//button[normalize-space()='Next' and not(@disabled)]"),
        )) > 0)
        ->press('Next')
        ->waitForText('Crop thumbnail (1:1)')
        ->waitUsing(10, 100, fn () => count($browser->driver->findElements(
            WebDriverBy::xpath("//button[normalize-space()='Save cover photo' and not(@disabled)]"),
        )) > 0)
        ->press('Save cover photo');
}

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('uploads a non-cover photo via observation and shows it in the gallery', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Gallery Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->assertSee('No photos yet')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', galleryFixture())
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitUntilMissingText('No photos yet');
    });

    $plant->refresh();
    expect($plant->photos()->count())->toBe(1);
    expect($plant->cover_photo_id)->toBeNull();
});

it('opens a gallery photo in a new tab when clicked', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk View Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', galleryFixture())
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitUntilMissingText('No photos yet');

        $initialHandles = $browser->driver->getWindowHandles();

        $browser->driver->findElement(
            WebDriverBy::cssSelector('.grid-cols-3 button[type="button"]'),
        )->click();

        $browser->waitUsing(5, 100, fn () => count($browser->driver->getWindowHandles()) > count($initialHandles));

        $allHandles = $browser->driver->getWindowHandles();
        $newHandle  = array_values(array_diff($allHandles, $initialHandles))[0];

        $browser->driver->switchTo()->window($newHandle);
        $url = $browser->driver->getCurrentURL();
        $browser->driver->close();
        $browser->driver->switchTo()->window(array_values($initialHandles)[0]);

        expect($url)->toContain('/uploads/');
    });
});

it('sets an existing gallery photo as cover from the cover photo modal', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Cover Pick Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', galleryFixture())
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitUntilMissingText('No photos yet');

        $browser->click('@change-cover')
            ->waitForText('Cover photo')
            ->waitForText('Pick from photos on this plant');

        $browser->driver->findElement(
            WebDriverBy::cssSelector('button[aria-pressed]'),
        )->click();

        $browser->waitUsing(15, 250, fn () => ! str_contains(
            $browser->attribute('@cover-hero', 'src') ?? '',
            'plant-silhouette',
        ));
    });

    $plant->refresh();
    expect($plant->cover_photo_id)->not->toBeNull();
});

it('uploads a new cover photo through the cover modal with crop workflow', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Cover Upload Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', galleryFixture())
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitUntilMissingText('No photos yet');

        $browser->click('@change-cover')
            ->waitForText('Cover photo')
            ->waitForText('Pick from photos on this plant')
            ->attach('@cover-upload-input', galleryFixture());

        completeCropWorkflow($browser);

        $browser->waitUsing(15, 250, fn () => str_contains(
            $browser->attribute('@cover-hero', 'src') ?? '',
            '_hero.webp',
        ));
    });

    $plant->refresh();
    expect($plant->cover_photo_id)->not->toBeNull();
    $cover = Photo::findOrFail($plant->cover_photo_id);
    expect($cover->path)->toEndWith('_hero.webp');
    expect($cover->thumb_path)->toEndWith('_thumb.webp');
});
