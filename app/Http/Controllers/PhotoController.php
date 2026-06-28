<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\Plant;
use App\Support\ImageProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PhotoController extends Controller
{
    public function index(Plant $plant): AnonymousResourceCollection
    {
        return PhotoResource::collection(
            $plant->photos()->latest('taken_on')->get()
        );
    }

    public function store(StorePhotoRequest $request, Plant $plant, ImageProcessor $processor): JsonResponse
    {
        $file = $request->file('photo');
        $heroCrop = $request->heroCrop();
        $thumbCrop = $request->thumbCrop();

        if ($heroCrop && $thumbCrop) {
            $processed = $processor->processCoverPhoto($file, $heroCrop, $thumbCrop);

            $photo = $plant->photos()->create([
                'disk' => 'photos',
                'path' => $processed['hero_path'],
                'thumb_path' => $processed['thumb_path'],
                'original_filename' => $file->getClientOriginalName(),
                'taken_on' => $request->date('taken_on') ?? now(),
                'caption' => $request->string('caption')->value() ?: null,
                'care_event_id' => $request->input('care_event_id'),
            ]);
        } else {
            $path = $file->store('', 'photos');

            $photo = $plant->photos()->create([
                'disk' => 'photos',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'taken_on' => $request->date('taken_on') ?? now(),
                'caption' => $request->string('caption')->value() ?: null,
                'care_event_id' => $request->input('care_event_id'),
            ]);
        }

        if ($request->boolean('set_as_cover')) {
            $plant->update(['cover_photo_id' => $photo->id]);
        }

        return PhotoResource::make($photo)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Photo $photo): Response
    {
        $plant = $photo->plant;
        $disk = $photo->disk;
        $path = $photo->path;
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

        return response()->noContent();
    }
}
