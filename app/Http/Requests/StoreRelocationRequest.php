<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRelocationRequest extends FormRequest
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
            'occurred_at' => ['nullable', 'date'],
            'to_location_id' => ['required', 'integer', Rule::exists('locations', 'id')],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
