<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesCareEvents;
use App\Http\Requests\StoreObservationRequest;
use App\Http\Resources\CareEventResource;
use App\Models\Plant;
use App\Support\SymptomResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ObservationController extends Controller
{
    use AuthorizesRequests;
    use WritesCareEvents;

    public function store(StoreObservationRequest $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $event = DB::transaction(function () use ($request, $plant) {
            $event = $this->newCareEvent($plant, 'observation', $request);

            $observation = $event->observation()->create([
                'overall_health' => $request->input('overall_health'),
                'health_note' => $request->filled('health_note') ? $request->string('health_note')->value() : null,
                'light_level' => $request->input('light_level'),
                'growth_rate' => $request->input('growth_rate'),
                'growth_note' => $request->filled('growth_note') ? $request->string('growth_note')->value() : null,
                'leaf_size_mm' => $request->input('leaf_size_mm'),
                'weight_grams' => $this->gramsFromComponents($request->input('weight')),
            ]);

            $symptomIds = SymptomResolver::resolveIds(
                $request->input('symptom_ids') ?? [],
                $request->input('custom_symptoms') ?? [],
            );

            if ($symptomIds !== []) {
                $observation->symptoms()->sync($symptomIds);
            }

            return $event;
        });

        return CareEventResource::make($event->load(['careEventType', 'observation.symptoms']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
