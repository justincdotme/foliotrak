<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlantStatus;
use App\Http\Requests\InsightsGroupRequest;
use App\Models\Location;
use App\Models\Plant;
use App\Models\Tag;
use App\Support\CorrelationEngine;
use App\Support\Trends;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class GroupInsightsController extends Controller
{
    use AuthorizesRequests;

    public function index(InsightsGroupRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Plant::class);

        $plantRelations = array_values(array_unique(['observationEvents.observation', ...CorrelationEngine::plantRelations()]));

        $tag = $request->filled('tag') ? Tag::findOrFail($request->integer('tag')) : null;
        $location = $request->filled('location') ? Location::findOrFail($request->integer('location')) : null;

        $query = Plant::where('status', PlantStatus::Active->value)->orderBy('id');

        if ($tag !== null) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('plant_tags.id', $tag->id));
        }

        if ($location !== null) {
            $query->where('location_id', $location->id);
        }

        $plants = $query->with($plantRelations)->get();

        $comparison = $plants->map(fn (Plant $plant): array => [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'health_trend' => Trends::health($plant->observationEvents),
            'watering_interval_days' => $plant->watering_interval_days_override,
            'fertilizer_interval_days' => $plant->fertilizing_interval_days_override,
        ])->all();

        $groupName = match (true) {
            $tag !== null && $location !== null => $tag->name.' in '.$location->name,
            $tag !== null => $tag->name,
            $location !== null => $location->name,
            default => 'All plants',
        };

        return response()->json(['data' => [
            'tag_id' => $tag?->id,
            'tag_name' => $tag?->name,
            'location_id' => $location?->id,
            'location_name' => $location?->name,
            'group_name' => $groupName,
            'plants' => $plants->pluck('id')->values()->all(),
            'comparison' => $comparison,
            'correlation_pairs' => CorrelationEngine::forPlants($plants),
        ]]);
    }

    public function locationSummary(): JsonResponse
    {
        $this->authorize('viewAny', Plant::class);

        $locations = Location::with(['plants' => function ($query): void {
            $query->where('status', PlantStatus::Active->value)
                ->with(['latestObservationEvent.observation']);
        }])->get();

        $summary = $locations->map(function (Location $location): array {
            $readings = $location->plants
                ->map(fn (Plant $plant): ?int => $plant->latestObservationEvent?->observation?->overall_health)
                ->filter()
                ->values();

            return [
                'location_id' => $location->id,
                'location_name' => $location->name,
                'plant_count' => $location->plants->count(),
                'mean_health' => $readings->count() > 0 ? round($readings->average(), 2) : null,
                'health_readings' => $readings->all(),
                'sample_size' => $readings->count(),
            ];
        });

        return response()->json($summary->values()->all());
    }
}
