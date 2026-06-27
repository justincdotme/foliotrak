<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LocationResource::collection(
            Location::query()->orderBy('name')->get()
        );
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create([
            'name' => trim($request->string('name')->value()),
        ]);

        return LocationResource::make($location)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
