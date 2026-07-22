<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Photo;
use App\Models\Plant;
use App\Support\ImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PhotoService
{
    /**
     * @param ImageProcessor $processor
     */
    public function __construct(private readonly ImageProcessor $processor) {}

    /**
     * @param Plant                                               $plant
     * @param UploadedFile                                        $file
     * @param Carbon|null                                         $takenOn
     * @param string|null                                         $caption
     * @param integer|null                                        $careEventId
     * @param boolean                                             $setAsCover
     * @param array{x: int, y: int, width: int, height: int}|null $heroCrop
     * @param array{x: int, y: int, width: int, height: int}|null $thumbCrop
     *
     * @return Photo
     */
    public function create(
        Plant $plant,
        UploadedFile $file,
        ?Carbon $takenOn,
        ?string $caption,
        ?int $careEventId,
        bool $setAsCover,
        ?array $heroCrop,
        ?array $thumbCrop,
    ): Photo {
        $paths = $this->resolvePaths($file, $heroCrop, $thumbCrop);

        $photo = $plant->photos()->create([
            'disk'              => 'photos',
            'path'              => $paths['path'],
            'thumb_path'        => $paths['thumb_path'],
            'original_filename' => $file->getClientOriginalName(),
            'taken_on'          => $takenOn ?? now(),
            'caption'           => $caption,
            'care_event_id'     => $careEventId,
        ]);

        if ($setAsCover) {
            $plant->update(['cover_photo_id' => $photo->id]);
        }

        return $photo;
    }

    /**
     * @param Photo $photo
     *
     * @return void
     */
    public function delete(Photo $photo): void
    {
        $plant     = $photo->plant;
        $disk      = $photo->disk;
        $path      = $photo->path;
        $thumbPath = $photo->thumb_path;

        DB::transaction(function () use ($plant, $photo): void {
            if ($plant->cover_photo_id === $photo->id) {
                $plant->update(['cover_photo_id' => null]);
            }

            $photo->delete();
        });

        Storage::disk($disk)->delete($path);

        if ($thumbPath) {
            Storage::disk($disk)->delete($thumbPath);
        }
    }

    /**
     * @param UploadedFile                                        $file
     * @param array{x: int, y: int, width: int, height: int}|null $heroCrop
     * @param array{x: int, y: int, width: int, height: int}|null $thumbCrop
     *
     * @return array{path: string, thumb_path: string|null}
     */
    private function resolvePaths(UploadedFile $file, ?array $heroCrop, ?array $thumbCrop): array
    {
        if ($heroCrop && $thumbCrop) {
            $processed = $this->processor->processCoverPhoto($file, $heroCrop, $thumbCrop);

            return [
                'path'       => $processed['hero_path'],
                'thumb_path' => $processed['thumb_path'],
            ];
        }

        return [
            'path'       => $file->store('', 'photos'),
            'thumb_path' => null,
        ];
    }
}
