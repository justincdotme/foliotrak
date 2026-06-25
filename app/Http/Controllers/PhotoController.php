<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\Plant;
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

    public function store(StorePhotoRequest $request, Plant $plant): JsonResponse
    {
        $file = $request->file('photo');
        $path = $file->store('', 'photos');

        $photo = $plant->photos()->create([
            'disk' => 'photos',
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'taken_on' => $request->date('taken_on') ?? now(),
            'caption' => $request->string('caption')->value() ?: null,
        ]);

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

        DB::transaction(function () use ($plant, $photo): void {
            // Clear the cover reference first so the row deletes cleanly under any driver.
            if ($plant->cover_photo_id === $photo->id) {
                $plant->update(['cover_photo_id' => null]);
            }

            $photo->delete();
        });

        // Delete the file only after the row is gone, so a storage failure can never
        // strand a database row that points at a missing file.
        Storage::disk($photo->disk)->delete($photo->path);

        return response()->noContent();
    }
}
