<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\RecordRelocation;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\CareEventSpine;
use App\Support\SymptomResolver;
use App\Support\Temperature;
use App\Support\Weight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CareEventService
{
    /**
     * @param RecordRelocation $recordRelocation
     */
    public function __construct(private readonly RecordRelocation $recordRelocation) {}

    /**
     * @param Plant                $plant
     * @param string               $type
     * @param array<string, mixed> $data
     * @param integer|null         $userId
     *
     * @return CareEvent|null
     */
    public function create(Plant $plant, string $type, array $data, ?int $userId): ?CareEvent
    {
        if ($type === 'relocation') {
            return $this->recordRelocation->record(
                $plant,
                isset($data['to_location_id']) ? (int) $data['to_location_id'] : null,
                empty($data['occurred_at']) ? null : Carbon::parse($data['occurred_at']),
                $this->blankToNull($data['note'] ?? null),
                $userId,
            );
        }

        return DB::transaction(function () use ($plant, $type, $data, $userId): CareEvent {
            $event = CareEventSpine::build(
                $plant,
                $type,
                $data['occurred_at'] ?? null,
                $userId,
                $this->blankToNull($data['note'] ?? null),
            );

            match ($type) {
                'watering'    => $this->writeWatering($event, $data),
                'fertilizing' => $this->writeFertilizing($event, $data),
                'repotting'   => $this->writeRepotting($event, $data),
                'observation' => $this->writeObservation($event, $data),
                default       => null,
            };

            return $event;
        });
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return CareEvent
     */
    public function update(CareEvent $event, array $data): CareEvent
    {
        $event->loadMissing('careEventType');
        $type = $event->careEventType->key;

        DB::transaction(function () use ($event, $type, $data): void {
            // Raw pass-through with no blank guard: an explicit empty string on a PATCH is
            // stored as '', not null, unlike blankToNull on create.
            $spine = $this->present($data, ['occurred_at', 'note']);

            if ($spine !== []) {
                $event->update($spine);
            }

            match ($type) {
                'watering'    => $this->editWatering($event, $data),
                'fertilizing' => $this->editFertilizing($event, $data),
                'repotting'   => $this->editRepotting($event, $data),
                'observation' => $this->editObservation($event, $data),
                'relocation'  => $this->editRelocation($event, $data),
                default       => null,
            };

            if ($type === 'relocation' && array_key_exists('occurred_at', $data)) {
                $this->recordRelocation->recomputeLocationFromChain($event->plant);
            }
        });

        return $event;
    }

    /**
     * @param CareEvent $event
     *
     * @return void
     */
    public function delete(CareEvent $event): void
    {
        $event->loadMissing('careEventType');
        $isRelocation = $event->careEventType->key === 'relocation';
        $plant        = $isRelocation ? $event->plant : null;

        DB::transaction(function () use ($event, $plant): void {
            // Detail rows and the symptom pivot drop with the spine via cascade FKs.
            $event->delete();

            if ($plant !== null) {
                $this->recordRelocation->recomputeLocationFromChain($plant);
            }
        });
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function writeWatering(CareEvent $event, array $data): void
    {
        $event->watering()->create([
            'amount_ml' => $this->nullableInt($data['amount_ml'] ?? null),
        ]);
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function writeFertilizing(CareEvent $event, array $data): void
    {
        $detail = $event->fertilizing()->create([
            'fertilizer_form_id' => $data['fertilizer_form_id'],
            'brand'              => $this->blankToNull($data['brand'] ?? null),
            'product'            => $this->blankToNull($data['product'] ?? null),
            'npk_n'              => $data['npk_n'] ?? null,
            'npk_p'              => $data['npk_p'] ?? null,
            'npk_k'              => $data['npk_k'] ?? null,
            'dose_pct'           => $this->nullableInt($data['dose_pct'] ?? null),
            'amount_ml'          => $this->nullableInt($data['amount_ml'] ?? null),
        ]);

        foreach ($data['nutrients'] ?? [] as $nutrient) {
            $detail->nutrients()->create([
                'nutrient_id' => $nutrient['nutrient_id'],
                'note'        => $nutrient['note'] ?? null,
            ]);
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function writeRepotting(CareEvent $event, array $data): void
    {
        $event->repotting()->create([
            'soil_recipe'      => $this->blankToNull($data['soil_recipe'] ?? null),
            'pot_size_value'   => $data['pot_size_value'] ?? null,
            'pot_size_unit'    => $this->blankToNull($data['pot_size_unit'] ?? null),
            'fertilizer_added' => filter_var($data['fertilizer_added'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function writeObservation(CareEvent $event, array $data): void
    {
        $observation = $event->observation()->create([
            'overall_health'         => $data['overall_health'] ?? null,
            'health_note'            => $this->blankToNull($data['health_note'] ?? null),
            'light_level'            => $data['light_level'] ?? null,
            'growth_rate'            => $data['growth_rate'] ?? null,
            'growth_note'            => $this->blankToNull($data['growth_note'] ?? null),
            'leaf_size_mm'           => $data['leaf_size_mm'] ?? null,
            'weight_grams'           => $this->gramsFromComponents($data['weight'] ?? null),
            'ambient_humidity_pct'   => $data['ambient_humidity_pct'] ?? null,
            'ambient_temp_c'         => $this->celsiusFromDisplay($data['ambient_temp'] ?? null),
            'ambient_lux'            => $data['ambient_lux'] ?? null,
            'soil_moisture_relative' => $data['soil_moisture_relative'] ?? null,
            'soil_moisture_precise'  => $data['soil_moisture_precise'] ?? null,
        ]);

        $symptomIds = SymptomResolver::resolveIds(
            $data['symptom_ids'] ?? [],
            $data['custom_symptoms'] ?? [],
        );

        if ($symptomIds !== []) {
            $observation->symptoms()->sync($symptomIds);
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function editWatering(CareEvent $event, array $data): void
    {
        if (array_key_exists('amount_ml', $data)) {
            $event->watering()->update(['amount_ml' => $data['amount_ml']]);
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function editFertilizing(CareEvent $event, array $data): void
    {
        // Unlike create, an edit passes these fields straight through with no blank guard:
        // present() forwards whatever was submitted, blank string or not.
        $detail = $this->present($data, ['fertilizer_form_id', 'brand', 'product', 'npk_n', 'npk_p', 'npk_k', 'dose_pct', 'amount_ml']);

        if ($detail !== []) {
            $event->fertilizing()->update($detail);
        }

        if (array_key_exists('nutrients', $data)) {
            $fertilizing = $event->fertilizing;
            $fertilizing->nutrients()->delete();

            foreach ($data['nutrients'] ?? [] as $nutrient) {
                $fertilizing->nutrients()->create([
                    'nutrient_id' => $nutrient['nutrient_id'],
                    'note'        => $nutrient['note'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function editRepotting(CareEvent $event, array $data): void
    {
        $detail = $this->present($data, ['soil_recipe', 'pot_size_value', 'pot_size_unit']);

        if (array_key_exists('fertilizer_added', $data)) {
            $detail['fertilizer_added'] = filter_var($data['fertilizer_added'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($detail !== []) {
            $event->repotting()->update($detail);
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function editObservation(CareEvent $event, array $data): void
    {
        $detail = $this->present($data, ['overall_health', 'health_note', 'light_level', 'growth_rate', 'growth_note', 'leaf_size_mm', 'ambient_humidity_pct', 'ambient_lux', 'soil_moisture_relative', 'soil_moisture_precise']);

        if (array_key_exists('weight', $data)) {
            $detail['weight_grams'] = $this->gramsFromComponents($data['weight']);
        }

        if (array_key_exists('ambient_temp', $data)) {
            $detail['ambient_temp_c'] = $this->celsiusFromDisplay($data['ambient_temp']);
        }

        if ($detail !== []) {
            $event->observation()->update($detail);
        }

        if (array_key_exists('symptom_ids', $data) || array_key_exists('custom_symptoms', $data)) {
            $event->observation->symptoms()->sync(SymptomResolver::resolveIds(
                $data['symptom_ids'] ?? [],
                $data['custom_symptoms'] ?? [],
            ));
        }
    }

    /**
     * @param CareEvent            $event
     * @param array<string, mixed> $data
     *
     * @return void
     */
    private function editRelocation(CareEvent $event, array $data): void
    {
        $detail = $this->present($data, ['from_location_id', 'to_location_id']);

        if ($detail === []) {
            return;
        }

        $event->relocation()->update($detail);
        $this->recordRelocation->recomputeLocationFromChain($event->plant);
    }

    /**
     * Extract present keys from data.
     *
     * @param array<string, mixed> $data
     * @param list<string>         $keys
     *
     * @return array<string, mixed>
     */
    private function present(array $data, array $keys): array
    {
        return array_intersect_key($data, array_flip($keys));
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    private function blankToNull(mixed $value): ?string
    {
        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Treats an absent or blank value as unset rather than zero, matching how amount_ml and dose_pct are recorded on create.
     *
     * @param mixed $value
     *
     * @return integer|null
     */
    private function nullableInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /**
     * @param array<string, mixed>|null $weight
     *
     * @return integer|null
     */
    private function gramsFromComponents(?array $weight): ?int
    {
        if (! is_array($weight)) {
            return null;
        }

        $grams = Weight::fromComponents(
            (float) ($weight['lb'] ?? 0),
            (float) ($weight['oz'] ?? 0),
            (float) ($weight['g'] ?? 0),
        )->grams;

        return $grams > 0 ? $grams : null;
    }

    /**
     * @param mixed $value
     *
     * @return float|null
     */
    private function celsiusFromDisplay(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round(
            Temperature::fromDisplay((float) $value, config('foliotrak.temperature_unit'))->celsius,
            1,
        );
    }
}
