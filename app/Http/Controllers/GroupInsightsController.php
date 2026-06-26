<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InsightsGroupRequest;
use App\Models\Plant;
use App\Models\Tag;
use App\Support\Trends;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class GroupInsightsController extends Controller
{
    use AuthorizesRequests;

    public function index(InsightsGroupRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Plant::class);

        $tag = Tag::query()
            ->with([
                'plants' => fn ($query) => $query->orderBy('id'),
                'plants.careEvents' => fn ($query) => $query->whereHas(
                    'careEventType',
                    fn ($type) => $type->where('key', 'observation')
                ),
                'plants.careEvents.observation',
            ])
            ->findOrFail($request->integer('tag'));

        $comparison = $tag->plants->map(fn (Plant $plant): array => [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'health_trend' => Trends::health($plant->careEvents),
            'watering_interval_days' => $plant->watering_interval_days_override,
            'fertilizer_interval_days' => $plant->fertilizing_interval_days_override,
        ])->all();

        return response()->json([
            'data' => [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
                'plants' => $tag->plants->pluck('id')->all(),
                'comparison' => $comparison,
                // This endpoint reports the descriptive comparison only; correlation pairs are computed elsewhere.
                'correlation_pairs' => [],
            ],
        ]);
    }
}
