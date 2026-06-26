<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRepottingRequest extends FormRequest
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
            'soil_recipe' => ['nullable', 'string', 'max:65535'],
            'pot_size_value' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'pot_size_unit' => ['nullable', Rule::in(['in', 'cm'])],
            'fertilizer_added' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
