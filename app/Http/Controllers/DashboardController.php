<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PlantStatus;
use App\Http\Resources\DueForCareResource;
use App\Http\Resources\UserResource;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\Care\CareDue;
use App\Support\ProblemFlagger;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    private const PLANT_RELATIONS = [
        'wateringEvents',
        'fertilizingEvents',
        'latestObservationEvent.observation.symptoms',
    ];

    private const RECENT_ACTIVITY_LIMIT = 8;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Plant::class);

        $plants = Plant::query()
            ->where('status', PlantStatus::Active->value)
            ->with(self::PLANT_RELATIONS)
            ->get();

        $dueForCare = $plants
            ->flatMap(fn (Plant $plant) => collect(CareDue::forPlant($plant))
                ->map(fn (CareDue $due): array => ['plant' => $plant, 'due' => $due]))
            ->sortBy(fn (array $entry): int => $entry['due']->daysLeft)
            ->values()
            ->map(fn (array $entry): array => (new DueForCareResource($entry['plant'], $entry['due']))->resolve($request))
            ->all();

        $flaggedProblems = $plants
            ->map(fn (Plant $plant): ?array => ProblemFlagger::forPlant($plant))
            ->filter()
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'user' => (new UserResource($request->user()))->resolve($request),
                'due_for_care' => $dueForCare,
                'recent_activity' => $this->recentActivity(),
                'flagged_problems' => $flaggedProblems,
            ],
        ]);
    }

    /**
     * @return list<array{event_id: int, plant_id: int, plant_common_name: string|null, type: string, occurred_at: string, note: string|null}>
     */
    private function recentActivity(): array
    {
        return CareEvent::query()
            ->whereHas('plant')
            ->with(['plant', 'careEventType'])
            ->orderByDesc('occurred_at')
            ->orderBy('id')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get()
            ->map(fn (CareEvent $event): array => [
                'event_id' => $event->id,
                'plant_id' => $event->plant_id,
                'plant_common_name' => $event->plant?->common_name,
                'type' => $event->careEventType->key,
                'occurred_at' => $event->occurred_at->toISOString(),
                'note' => $event->note,
            ])
            ->all();
    }
}
