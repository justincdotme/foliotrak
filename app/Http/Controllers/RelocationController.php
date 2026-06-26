<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordRelocation;
use App\Http\Requests\StoreRelocationRequest;
use App\Http\Resources\CareEventResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RelocationController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreRelocationRequest $request, Plant $plant, RecordRelocation $recordRelocation): JsonResponse|Response
    {
        $this->authorize('update', $plant);

        $event = $recordRelocation->record(
            $plant,
            $request->string('to_location')->value(),
            $request->date('occurred_at'),
            $request->filled('note') ? $request->string('note')->value() : null,
            $request->user()?->id,
        );

        // An unchanged location is a no-op (single-writer): nothing was logged.
        if ($event === null) {
            return response()->noContent();
        }

        return CareEventResource::make($event->load(['careEventType', 'relocation']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
