<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFertilizingRequest extends FormRequest
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
            'fertilizer_form_id' => ['required', 'integer', Rule::exists('fertilizer_forms', 'id')],
            'brand' => ['nullable', 'string', 'max:128'],
            'product' => ['nullable', 'string', 'max:191'],
            'npk_n' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'npk_p' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'npk_k' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'dose_pct' => ['nullable', 'integer', 'min:0', 'max:255'],
            'amount_ml' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'note' => ['nullable', 'string', 'max:65535'],
            'nutrients' => ['nullable', 'array'],
            'nutrients.*.nutrient_id' => ['required', 'integer', Rule::exists('nutrients', 'id')],
            'nutrients.*.note' => ['nullable', 'string', 'max:128'],
        ];
    }
}
