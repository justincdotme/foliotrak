<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCareEventRequest;
use App\Http\Requests\UpdateCareEventRequest;
use App\Http\Resources\CareEventResource;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Services\CareEventService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CareEventController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly CareEventService $service) {}

    public function store(StoreCareEventRequest $request, Plant $plant): JsonResponse|Response
    {
        $this->authorize('update', $plant);

        $type = $request->string('type')->value();
        $event = $this->service->create($plant, $type, $request->safe()->except('type'), $request->user()?->id);

        if ($event === null) {
            return response()->noContent();
        }

        return CareEventResource::make($event->load(['careEventType', ...CareEventResource::detailRelations($type)]))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateCareEventRequest $request, CareEvent $event): CareEventResource
    {
        $this->authorize('update', $event->plant);

        $event->loadMissing('careEventType');
        $typeKey = $event->careEventType->key;

        $this->service->update($event, $request->safe()->all());

        return CareEventResource::make(
            $event->fresh()->load(['careEventType', ...CareEventResource::detailRelations($typeKey)])
        );
    }

    public function destroy(CareEvent $event): Response
    {
        $this->authorize('delete', $event->plant);

        $this->service->delete($event);

        return response()->noContent();
    }
}
