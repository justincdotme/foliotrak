<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesCareEvents;
use App\Http\Requests\StoreWateringRequest;
use App\Http\Resources\CareEventResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class WateringController extends Controller
{
    use AuthorizesRequests;
    use WritesCareEvents;

    public function store(StoreWateringRequest $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $event = DB::transaction(function () use ($request, $plant) {
            $event = $this->newCareEvent($plant, 'watering', $request);
            $event->watering()->create([
                'amount_ml' => $request->filled('amount_ml') ? $request->integer('amount_ml') : null,
            ]);

            return $event;
        });

        return CareEventResource::make($event->load(['careEventType', 'watering']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
