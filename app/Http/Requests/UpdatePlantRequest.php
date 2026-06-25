<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PlantStatus;
use App\Models\Plant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlantRequest extends FormRequest
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
        $plant = $this->route('plant');
        $plantId = $plant instanceof Plant ? $plant->getKey() : null;

        return [
            'common_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'scientific_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gbif_key' => ['sometimes', 'nullable', 'string', 'max:64'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'acquired_on' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::enum(PlantStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'watering_interval_days_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'fertilizing_interval_days_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            // A cover must be one of this plant's own photos; null clears it (D25).
            'cover_photo_id' => ['sometimes', 'nullable', 'integer', Rule::exists('photos', 'id')->where('plant_id', $plantId)],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('plant_tags', 'id')],
        ];
    }
}
