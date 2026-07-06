<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSensorRequest extends FormRequest
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
            'mac'         => ['required', 'string', Rule::unique('sensors', 'mac')],
            'device_name' => ['nullable', 'string'],
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string', 'max:255'],
        ];
    }
}
