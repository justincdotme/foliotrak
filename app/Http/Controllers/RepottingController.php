<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesCareEvents;
use App\Http\Requests\StoreRepottingRequest;
use App\Http\Resources\CareEventResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RepottingController extends Controller
{
    use AuthorizesRequests;
    use WritesCareEvents;

    public function store(StoreRepottingRequest $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $event = DB::transaction(function () use ($request, $plant) {
            $event = $this->newCareEvent($plant, 'repotting', $request);
            $event->repotting()->create([
                'soil_recipe' => $request->filled('soil_recipe') ? $request->string('soil_recipe')->value() : null,
                'pot_size_value' => $request->input('pot_size_value'),
                'pot_size_unit' => $request->filled('pot_size_unit') ? $request->string('pot_size_unit')->value() : null,
                'fertilizer_added' => $request->boolean('fertilizer_added'),
            ]);

            return $event;
        });

        return CareEventResource::make($event->load(['careEventType', 'repotting']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
