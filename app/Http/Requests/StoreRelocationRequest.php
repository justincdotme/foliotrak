<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'to_location' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:65535'],
        ];
    }
}
