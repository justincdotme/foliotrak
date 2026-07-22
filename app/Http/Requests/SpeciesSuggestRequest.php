<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpeciesSuggestRequest extends FormRequest
{
    /**
     * @return boolean
     */
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
            'q'     => ['required', 'string', 'min:3', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
