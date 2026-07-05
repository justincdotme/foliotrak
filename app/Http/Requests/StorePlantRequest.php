<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlantStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantRequest extends FormRequest
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
            'common_name' => ['nullable', 'string', 'max:255'],
            'scientific_name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'gbif_key' => ['nullable', 'string', 'max:64'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'acquired_on' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(PlantStatus::class)],
            'notes' => ['nullable', 'string'],
            'watering_interval_days_override' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'watering_schedule_start_date' => ['nullable', 'date'],
            'fertilizing_interval_days_override' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('plant_tags', 'id')],
            'equipment_ids' => ['sometimes', 'array'],
            'equipment_ids.*' => ['integer', Rule::exists('equipment', 'id')],
            'sensor_ids' => ['sometimes', 'array'],
            'sensor_ids.*' => ['integer', Rule::exists('sensors', 'id')],
        ];
    }
}
