<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
            'careEvents.watering',
            'careEvents.fertilizing.fertilizerForm',
            'careEvents.fertilizing.nutrients.nutrient',
            'careEvents.repotting',
            'careEvents.observation.symptoms',
            'careEvents.relocation.fromLocation',
            'careEvents.relocation.toLocation',
        ]);

        return new PlantTimelineResource($plant);
    }
}
