<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesCareEvents;
use App\Http\Requests\StoreFertilizingRequest;
use App\Http\Resources\CareEventResource;
use App\Models\Plant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class FertilizingController extends Controller
{
    use AuthorizesRequests;
    use WritesCareEvents;

    public function store(StoreFertilizingRequest $request, Plant $plant): JsonResponse
    {
        $this->authorize('update', $plant);

        $event = DB::transaction(function () use ($request, $plant) {
            $event = $this->newCareEvent($plant, 'fertilizing', $request);

            $detail = $event->fertilizing()->create([
                'fertilizer_form_id' => $request->integer('fertilizer_form_id'),
                'brand' => $request->filled('brand') ? $request->string('brand')->value() : null,
                'product' => $request->filled('product') ? $request->string('product')->value() : null,
                'npk_n' => $request->input('npk_n'),
                'npk_p' => $request->input('npk_p'),
                'npk_k' => $request->input('npk_k'),
                'dose_pct' => $request->filled('dose_pct') ? $request->integer('dose_pct') : null,
                'amount_ml' => $request->filled('amount_ml') ? $request->integer('amount_ml') : null,
            ]);

            foreach ($request->input('nutrients') ?? [] as $nutrient) {
                $detail->nutrients()->create([
                    'nutrient_id' => $nutrient['nutrient_id'],
                    'note' => $nutrient['note'] ?? null,
                ]);
            }

            return $event;
        });

        return CareEventResource::make(
            $event->load(['careEventType', 'fertilizing.fertilizerForm', 'fertilizing.nutrients.nutrient'])
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
