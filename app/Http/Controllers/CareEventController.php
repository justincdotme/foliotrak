<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\WritesCareEvents;
use App\Http\Requests\UpdateCareEventRequest;
use App\Http\Resources\CareEventResource;
use App\Models\CareEvent;
use App\Support\SymptomResolver;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CareEventController extends Controller
{
    use AuthorizesRequests;
    use WritesCareEvents;

    public function update(UpdateCareEventRequest $request, CareEvent $event): CareEventResource
    {
        $this->authorize('update', $event->plant);
        $event->loadMissing('careEventType');
        $typeKey = $event->careEventType->key;

        DB::transaction(function () use ($request, $event, $typeKey): void {
            $spine = $request->safe()->only(['occurred_at', 'note']);
            if ($spine !== []) {
                $event->update($spine);
            }

            match ($typeKey) {
                'watering' => $this->updateWatering($event, $request),
                'fertilizing' => $this->updateFertilizing($event, $request),
                'repotting' => $this->updateRepotting($event, $request),
                'observation' => $this->updateObservation($event, $request),
                'relocation' => $this->updateRelocation($event, $request),
                default => null,
            };
        });

        return CareEventResource::make(
            $event->fresh()->load(['careEventType', ...$this->detailRelations($typeKey)])
        );
    }

    public function destroy(CareEvent $event): Response
    {
        $this->authorize('delete', $event->plant);

        // Detail rows and the symptom pivot drop with the spine via cascade FKs.
        $event->delete();

        return response()->noContent();
    }

    private function updateWatering(CareEvent $event, UpdateCareEventRequest $request): void
    {
        if ($request->has('amount_ml')) {
            $event->watering()->update(['amount_ml' => $request->input('amount_ml')]);
        }
    }

    private function updateFertilizing(CareEvent $event, UpdateCareEventRequest $request): void
    {
        $data = $this->present($request, ['fertilizer_form_id', 'brand', 'product', 'npk_n', 'npk_p', 'npk_k', 'dose_pct', 'amount_ml']);
        if ($data !== []) {
            $event->fertilizing()->update($data);
        }

        if ($request->has('nutrients')) {
            $detail = $event->fertilizing;
            $detail->nutrients()->delete();
            foreach ($request->input('nutrients') ?? [] as $nutrient) {
                $detail->nutrients()->create([
                    'nutrient_id' => $nutrient['nutrient_id'],
                    'note' => $nutrient['note'] ?? null,
                ]);
            }
        }
    }

    private function updateRepotting(CareEvent $event, UpdateCareEventRequest $request): void
    {
        $data = $this->present($request, ['soil_recipe', 'pot_size_value', 'pot_size_unit']);
        if ($request->has('fertilizer_added')) {
            $data['fertilizer_added'] = $request->boolean('fertilizer_added');
        }
        if ($data !== []) {
            $event->repotting()->update($data);
        }
    }

    private function updateObservation(CareEvent $event, UpdateCareEventRequest $request): void
    {
        $data = $this->present($request, ['overall_health', 'health_note', 'light_level', 'growth_rate', 'growth_note', 'leaf_size_mm']);
        if ($request->has('weight')) {
            $data['weight_grams'] = $this->gramsFromComponents($request->input('weight'));
        }
        if ($data !== []) {
            $event->observation()->update($data);
        }

        if ($request->has('symptom_ids') || $request->has('custom_symptoms')) {
            $event->observation->symptoms()->sync(SymptomResolver::resolveIds(
                $request->input('symptom_ids') ?? [],
                $request->input('custom_symptoms') ?? [],
            ));
        }
    }

    /**
     * Edits the recorded from/to of a logged move. When the edited event is the
     * plant's most recent relocation, its new destination is mirrored to
     * plants.location so the stored location never drifts from the latest move;
     * editing an older move leaves the current location untouched.
     */
    private function updateRelocation(CareEvent $event, UpdateCareEventRequest $request): void
    {
        $data = $this->present($request, ['from_location', 'to_location']);
        if ($data === []) {
            return;
        }

        $event->relocation()->update($data);

        if ($request->has('to_location') && $this->isLatestRelocation($event)) {
            $event->plant->update(['location' => $request->input('to_location')]);
        }
    }

    private function isLatestRelocation(CareEvent $event): bool
    {
        return ! $event->plant->careEvents()
            ->whereHas('careEventType', fn ($type) => $type->where('key', 'relocation'))
            ->where('id', '!=', $event->id)
            ->where(fn ($newer) => $newer
                ->where('occurred_at', '>', $event->occurred_at)
                ->orWhere(fn ($tie) => $tie
                    ->where('occurred_at', $event->occurred_at)
                    ->where('id', '>', $event->id)))
            ->exists();
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function present(UpdateCareEventRequest $request, array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            if ($request->has($key)) {
                $data[$key] = $request->input($key);
            }
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    private function detailRelations(string $typeKey): array
    {
        return match ($typeKey) {
            'watering' => ['watering'],
            'fertilizing' => ['fertilizing.fertilizerForm', 'fertilizing.nutrients.nutrient'],
            'repotting' => ['repotting'],
            'observation' => ['observation.symptoms'],
            'relocation' => ['relocation'],
            default => [],
        };
    }
}
