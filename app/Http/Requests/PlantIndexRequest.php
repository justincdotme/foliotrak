<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlantIndexRequest extends FormRequest
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
            'sort'      => ['nullable', Rule::in(['name', 'last_watered'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
