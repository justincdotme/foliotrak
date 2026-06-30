<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

class ImageProcessor
{
    public function __construct(private ImageManager $manager) {}

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $heroCrop
     * @param  array{x: int, y: int, width: int, height: int}  $thumbCrop
     * @return array{hero_path: string, thumb_path: string}
     */
    public function processCoverPhoto(UploadedFile $file, array $heroCrop, array $thumbCrop): array
    {
        $sourcePath = $file->getRealPath();
        $uuid = Str::uuid()->toString();
        $quality = (int) config('foliotrak.photos.webp_quality', 85);

        $source = $this->manager->decodePath($sourcePath);
        $source->orient();

        $heroPath = $uuid.'_hero.webp';
        $hero = clone $source;
        $hero->crop($heroCrop['width'], $heroCrop['height'], x: $heroCrop['x'], y: $heroCrop['y']);
        $hero->scaleDown(
            width: (int) config('foliotrak.photos.hero_width', 600),
            height: (int) config('foliotrak.photos.hero_height', 900),
        );
        Storage::disk('photos')->put($heroPath, (string) $hero->encodeUsingFormat(Format::WEBP, quality: $quality));
        unset($hero);

        $thumbPath = $uuid.'_thumb.webp';
        $thumb = clone $source;
        $thumb->crop($thumbCrop['width'], $thumbCrop['height'], x: $thumbCrop['x'], y: $thumbCrop['y']);
        $thumb->scaleDown(
            width: (int) config('foliotrak.photos.thumb_width', 540),
            height: (int) config('foliotrak.photos.thumb_height', 540),
        );
        Storage::disk('photos')->put($thumbPath, (string) $thumb->encodeUsingFormat(Format::WEBP, quality: $quality));
        unset($thumb, $source);

        return ['hero_path' => $heroPath, 'thumb_path' => $thumbPath];
    }
}
