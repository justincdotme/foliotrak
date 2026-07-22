<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\GrowthRate;
use App\Enums\SoilMoistureLevel;
use Illuminate\Validation\Rule;

final class CareEventRuleSets
{
    /** Valid care event types. */
    public const TYPES = ['watering', 'fertilizing', 'repotting', 'observation', 'relocation'];

    /**
     * @param string $type
     *
     * @return array<string, mixed>
     */
    public static function create(string $type): array
    {
        return [...self::spine(false), ...self::detail($type, false)];
    }

    /**
     * @param string $type
     *
     * @return array<string, mixed>
     */
    public static function update(string $type): array
    {
        return [...self::spine(true), ...self::detail($type, true)];
    }

    /**
     * @param boolean $partial
     *
     * @return array<string, mixed>
     */
    private static function spine(bool $partial): array
    {
        return [
            'occurred_at' => $partial ? ['sometimes', 'date'] : ['required', 'date'],
            'note'        => self::opt($partial, ['string', 'max:65535']),
        ];
    }

    /**
     * @param string  $type
     * @param boolean $partial
     *
     * @return array<string, mixed>
     */
    private static function detail(string $type, bool $partial): array
    {
        return match ($type) {
            'watering' => [
                'amount_ml' => self::opt($partial, ['integer', 'min:0', 'max:4294967295']),
            ],
            'fertilizing' => [
                'fertilizer_form_id'      => self::req($partial, ['integer', Rule::exists('fertilizer_forms', 'id')]),
                'brand'                   => self::opt($partial, ['string', 'max:128']),
                'product'                 => self::opt($partial, ['string', 'max:191']),
                'npk_n'                   => self::opt($partial, ['numeric', 'min:0', 'max:999.99']),
                'npk_p'                   => self::opt($partial, ['numeric', 'min:0', 'max:999.99']),
                'npk_k'                   => self::opt($partial, ['numeric', 'min:0', 'max:999.99']),
                'dose_pct'                => self::opt($partial, ['integer', 'min:0', 'max:255']),
                'amount_ml'               => self::opt($partial, ['integer', 'min:0', 'max:4294967295']),
                'nutrients'               => self::opt($partial, ['array']),
                'nutrients.*.nutrient_id' => ['required', 'integer', Rule::exists('nutrients', 'id')],
                'nutrients.*.note'        => ['nullable', 'string', 'max:128'],
            ],
            'repotting' => [
                'soil_recipe'      => self::opt($partial, ['string', 'max:65535']),
                'pot_size_value'   => self::opt($partial, ['numeric', 'min:0', 'max:9999.9']),
                'pot_size_unit'    => self::opt($partial, [Rule::in(['in', 'cm'])]),
                'fertilizer_added' => ['sometimes', 'boolean'],
            ],
            'observation' => [
                'overall_health'         => self::opt($partial, ['integer', 'min:1', 'max:5']),
                'health_note'            => self::opt($partial, ['string', 'max:65535']),
                'light_level'            => self::opt($partial, ['integer', 'min:0', 'max:10']),
                'growth_rate'            => self::opt($partial, [Rule::enum(GrowthRate::class)]),
                'growth_note'            => self::opt($partial, ['string', 'max:65535']),
                'leaf_size_mm'           => self::opt($partial, ['numeric', 'min:0', 'max:99999.9']),
                'weight'                 => self::opt($partial, ['array']),
                'weight.lb'              => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'weight.oz'              => ['nullable', 'numeric', 'min:0', 'max:9999'],
                'weight.g'               => ['nullable', 'numeric', 'min:0', 'max:999999'],
                'ambient_humidity_pct'   => self::opt($partial, ['integer', 'min:0', 'max:100']),
                'ambient_temp'           => self::opt($partial, ['numeric', ...self::tempRange()]),
                'ambient_lux'            => self::opt($partial, ['numeric', 'min:0']),
                'soil_moisture_relative' => self::opt($partial, [Rule::enum(SoilMoistureLevel::class)]),
                'soil_moisture_precise'  => self::opt($partial, ['integer', 'min:1', 'max:10']),
                'symptom_ids'            => self::opt($partial, ['array']),
                'symptom_ids.*'          => ['integer', Rule::exists('symptoms', 'id')],
                'custom_symptoms'        => self::opt($partial, ['array']),
                'custom_symptoms.*'      => ['string', 'max:96'],
            ],
            'relocation' => self::relocation($partial),
            default      => [],
        };
    }

    /**
     * @param boolean $partial
     *
     * @return array<string, mixed>
     */
    private static function relocation(bool $partial): array
    {
        // Create: to_location_id required; occurred_at may be omitted (defaults to now server-side).
        // Update: both location ends editable; nothing required.
        return $partial
            ? [
                'to_location_id'   => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')],
                'from_location_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')],
            ]
            : [
                'occurred_at'    => ['nullable', 'date'],
                'to_location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            ];
    }

    /**
     * @return array<int, string>
     */
    private static function tempRange(): array
    {
        $unit = function_exists('config') && app()->bound('config')
            ? config('foliotrak.temperature_unit', 'F')
            : 'F';

        $lo = Temperature::fromCelsius(-50)->toDisplay($unit);
        $hi = Temperature::fromCelsius(60)->toDisplay($unit);

        return ["min:{$lo}", "max:{$hi}"];
    }

    /**
     * @param boolean           $partial
     * @param array<int, mixed> $rules
     *
     * @return array<int, mixed>
     */
    private static function opt(bool $partial, array $rules): array
    {
        return [...($partial ? ['sometimes', 'nullable'] : ['nullable']), ...$rules];
    }

    /**
     * @param boolean           $partial
     * @param array<int, mixed> $rules
     *
     * @return array<int, mixed>
     */
    private static function req(bool $partial, array $rules): array
    {
        return [...($partial ? ['sometimes'] : ['required']), ...$rules];
    }
}
