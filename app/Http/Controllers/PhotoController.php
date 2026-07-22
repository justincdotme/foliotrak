<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePhotoRequest;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\Plant;
use App\Services\PhotoService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PhotoController extends Controller
{
    use AuthorizesRequests;

    /**
     * @param PhotoService $service
     */
    public function __construct(private readonly PhotoService $service) {}

    /**
     * @param Plant $plant
     *
     * @return AnonymousResourceCollection
     */
    public function index(Plant $plant): AnonymousResourceCollection
    {
        return PhotoResource::collection(
            $plant->photos()->latest('taken_on')->get(),
        );
    }

    /**
     * @param StorePhotoRequest $request
     * @param Plant             $plant
     *
     * @return JsonResponse
     */
    public function store(StorePhotoRequest $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $photo = $this->service->create(
            plant: $plant,
            file: $request->file('photo'),
            takenOn: $request->date('taken_on'),
            caption: $request->string('caption')->value() ?: null,
            careEventId: $request->careEventId(),
            setAsCover: $request->boolean('set_as_cover'),
            heroCrop: $request->heroCrop(),
            thumbCrop: $request->thumbCrop(),
        );

        return PhotoResource::make($photo)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Photo $photo
     *
     * @return Response
     */
    public function destroy(Photo $photo): Response
    {
        $this->authorize('delete', $photo->plant);

        $this->service->delete($photo);

        return response()->noContent();
    }
}
