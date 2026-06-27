<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlantStatus;
use App\Http\Requests\InsightsGroupRequest;
use App\Models\Plant;
use App\Models\Tag;
use App\Support\CorrelationEngine;
use App\Support\Trends;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class GroupInsightsController extends Controller
{
    use AuthorizesRequests;

    public function index(InsightsGroupRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Plant::class);

        // The comparison reads health observations; the correlation factors declare what else
        // they need, so the eager-load set grows with the factors instead of being hardcoded.
        $with = ['plants' => fn ($query) => $query->where('status', PlantStatus::Active->value)->orderBy('id')];
        foreach (array_unique(['observationEvents.observation', ...CorrelationEngine::plantRelations()]) as $relation) {
            $with[] = "plants.{$relation}";
        }

        $tag = Tag::query()->with($with)->findOrFail($request->integer('tag'));

        $comparison = $tag->plants->map(fn (Plant $plant): array => [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'health_trend' => Trends::health($plant->observationEvents),
            'watering_interval_days' => $plant->watering_interval_days_override,
            'fertilizer_interval_days' => $plant->fertilizing_interval_days_override,
        ])->all();

        return response()->json([
            'data' => [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
                'plants' => $tag->plants->pluck('id')->all(),
                'comparison' => $comparison,
                'correlation_pairs' => CorrelationEngine::forPlants($tag->plants),
            ],
        ]);
    }
}
