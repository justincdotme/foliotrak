<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\CareEventResource;
use App\Http\Resources\PlantTimelineResource;
use App\Models\Plant;
use App\Support\Care\CareDue;
use App\Support\Trends;
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
            'observationEvents.observation',
            'photos' => fn ($query) => $query->orderByDesc('taken_on'),
            'careEvents' => fn ($query) => $query->orderByDesc('occurred_at')->orderBy('id'),
            'careEvents.careEventType',
            ...array_map(fn (string $r) => "careEvents.$r", CareEventResource::allDetailRelations()),
        ]);

        $observations = $plant->observationEvents;
        $trends = [
            'health' => Trends::health($observations),
            'weight' => Trends::weight($observations),
            'growth' => Trends::growth($observations),
            'light' => Trends::light($observations),
            'leaf_size' => Trends::leafSize($observations),
        ];

        return new PlantTimelineResource($plant, $trends, $plant->condition(), CareDue::forPlant($plant));
    }
}
