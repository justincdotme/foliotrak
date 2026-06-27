<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\GrowthRate;
use App\Enums\SoilMoistureLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends FormRequest
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
            'occurred_at' => ['required', 'date'],
            'overall_health' => ['nullable', 'integer', 'min:1', 'max:5'],
            'health_note' => ['nullable', 'string', 'max:65535'],
            'light_level' => ['nullable', 'integer', 'min:0', 'max:10'],
            'growth_rate' => ['nullable', Rule::enum(GrowthRate::class)],
            'growth_note' => ['nullable', 'string', 'max:65535'],
            'leaf_size_mm' => ['nullable', 'numeric', 'min:0', 'max:99999.9'],
            'weight' => ['nullable', 'array'],
            'weight.lb' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'weight.oz' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'weight.g' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ambient_humidity_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ambient_temp' => ['nullable', 'numeric', 'min:-50', 'max:60'],
            'soil_moisture_relative' => ['nullable', Rule::enum(SoilMoistureLevel::class)],
            'soil_moisture_precise' => ['nullable', 'integer', 'min:1', 'max:10'],
            'symptom_ids' => ['nullable', 'array'],
            'symptom_ids.*' => ['integer', Rule::exists('symptoms', 'id')],
            'custom_symptoms' => ['nullable', 'array'],
            'custom_symptoms.*' => ['string', 'max:96'],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
