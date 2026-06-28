<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\RecommendationResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PlantRecommendationController extends Controller
{
    use AuthorizesRequests;

    public function show(Plant $plant): RecommendationResource
    {
        $this->authorize('view', $plant);

        $plant->load([
            'careEvents',
            'location',
            'wateringEvents.watering',
            'observationEvents.observation.symptoms',
            'relocationEvents.relocation.fromLocation',
            'relocationEvents.relocation.toLocation',
        ]);

        return new RecommendationResource($plant);
    }
}
