<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWateringRequest extends FormRequest
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
            'amount_ml' => ['nullable', 'integer', 'min:0', 'max:4294967295'],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
