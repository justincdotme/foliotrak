<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTagRequest extends FormRequest
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
            'name'  => ['sometimes', 'required', 'string', 'max:64', Rule::unique('plant_tags', 'name')->ignore($this->route('tag'))],
            'color' => ['sometimes', 'nullable', 'string', 'max:16'],
        ];
    }
}
