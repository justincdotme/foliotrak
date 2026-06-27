<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GrowthRate;
use App\Enums\SoilMoistureLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an edit to any care event. The fields are the union across event
 * types; the controller applies only those relevant to the event's own type.
 */
class UpdateCareEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'occurred_at' => ['sometimes', 'date'],
            'note' => ['sometimes', 'nullable', 'string', 'max:65535'],

            'amount_ml' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:4294967295'],

            'fertilizer_form_id' => ['sometimes', 'integer', Rule::exists('fertilizer_forms', 'id')],
            'brand' => ['sometimes', 'nullable', 'string', 'max:128'],
            'product' => ['sometimes', 'nullable', 'string', 'max:191'],
            'npk_n' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'npk_p' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'npk_k' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'dose_pct' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'nutrients' => ['sometimes', 'nullable', 'array'],
            'nutrients.*.nutrient_id' => ['required', 'integer', Rule::exists('nutrients', 'id')],
            'nutrients.*.note' => ['nullable', 'string', 'max:128'],

            'soil_recipe' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'pot_size_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.9'],
            'pot_size_unit' => ['sometimes', 'nullable', Rule::in(['in', 'cm'])],
            'fertilizer_added' => ['sometimes', 'boolean'],

            'overall_health' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'health_note' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'light_level' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'growth_rate' => ['sometimes', 'nullable', Rule::enum(GrowthRate::class)],
            'growth_note' => ['sometimes', 'nullable', 'string', 'max:65535'],
            'leaf_size_mm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.9'],
            'weight' => ['sometimes', 'nullable', 'array'],
            'weight.lb' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'weight.oz' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'weight.g' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ambient_humidity_pct' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100'],
            'ambient_temp' => ['sometimes', 'nullable', 'numeric', 'min:-50', 'max:60'],
            'soil_moisture_relative' => ['sometimes', 'nullable', Rule::enum(SoilMoistureLevel::class)],
            'soil_moisture_precise' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10'],
            'symptom_ids' => ['sometimes', 'nullable', 'array'],
            'symptom_ids.*' => ['integer', Rule::exists('symptoms', 'id')],
            'custom_symptoms' => ['sometimes', 'nullable', 'array'],
            'custom_symptoms.*' => ['string', 'max:96'],

            'to_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from_location' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
