<?php

declare(strict_types=1);

use App\Models\Photo;
use App\Models\Plant;
use App\Models\User;
use Facebook\WebDriver\WebDriverBy;
use Laravel\Dusk\Browser;

/**
 * A generated fixture avoids committing a binary; GD is compiled into the app image.
 *
 * @return string
 */
function coverFixture(): string
{
    $path = sys_get_temp_dir() . '/dusk-cover.jpg';

    if (! file_exists($path)) {
        $img = imagecreatetruecolor(900, 1400);
        imagefilledrectangle($img, 0, 0, 900, 1400, (int) imagecolorallocate($img, 34, 139, 34));
        imagejpeg($img, $path, 85);
        imagedestroy($img);
    }

    return $path;
}

/**
 * react-easy-crop emits an initial crop area on image load, so the step buttons
 * enable without a drag; the waits cover the load plus the webp encodes on save.
 *
 * @param Browser $browser
 *
 * @return void
 */
function completeCrop(Browser $browser): void
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

it('crops a cover photo while adding a plant', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit('/plants')
            ->waitFor('@add-plant')
            ->click('@add-plant')
            ->waitFor('@add-plant-modal')
            ->type('@add-plant-name', 'Dusk Cropper Plant')
            ->attach('@add-plant-photo', coverFixture());

        completeCrop($browser);

        $browser->waitForText('(cropped)')
            ->click('@add-plant-submit')
            ->waitUntilMissing('@add-plant-modal', 15)
            ->waitFor('@plant-card')
            ->waitUsing(15, 250, fn () => str_contains(
                $browser->attribute('@plant-card img', 'src') ?? '',
                '_thumb.webp',
            ));
    });

    $plant = Plant::where('common_name', 'Dusk Cropper Plant')->firstOrFail();
    expect($plant->cover_photo_id)->not->toBeNull();
    $cover = Photo::findOrFail($plant->cover_photo_id);
    expect($cover->thumb_path)->toEndWith('_thumb.webp');
    expect($cover->path)->toEndWith('_hero.webp');
});

it('crops a replacement cover photo from the plant detail page', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Cover Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->resize(1280, 800)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@change-cover')
            ->click('@change-cover')
            ->waitForText('Cover photo')
            ->attach('@cover-upload-input', coverFixture());

        completeCrop($browser);

        $browser->waitUsing(15, 250, fn () => str_contains(
            $browser->attribute('@cover-hero', 'src') ?? '',
            '_hero.webp',
        ));
    });

    $plant->refresh();
    expect($plant->cover_photo_id)->not->toBeNull();
    expect(Photo::findOrFail($plant->cover_photo_id)->path)->toEndWith('_hero.webp');
});
