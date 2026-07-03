<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CareEventResource;
use App\Http\Resources\PlantTimelineResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PlantTimelineController extends Controller
{
    use AuthorizesRequests;

    public function show(Plant $plant): PlantTimelineResource
    {
        $this->authorize('view', $plant);

        $plant->load([
            'tags',
            'coverPhoto',
            'location',
            'latestObservationEvent.observation.symptoms',
            'wateringEvents',
            'fertilizingEvents',
            'photos' => fn ($query) => $query->orderByDesc('taken_on'),
            'careEvents' => fn ($query) => $query->orderByDesc('occurred_at')->orderBy('id'),
            'careEvents.careEventType',
            ...array_map(fn (string $r) => "careEvents.$r", CareEventResource::allDetailRelations()),
        ]);

        return new PlantTimelineResource($plant);
    }
}
