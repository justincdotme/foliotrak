<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InsightsGroupRequest extends FormRequest
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
            'tag' => ['nullable', 'integer', Rule::exists('plant_tags', 'id')],
            'location' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ];
    }
}
