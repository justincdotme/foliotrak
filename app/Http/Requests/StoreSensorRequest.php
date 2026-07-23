<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SensorType;
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
            'mac'           => ['required', 'string', 'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/', Rule::unique('sensors', 'mac')],
            'device_name'   => ['nullable', 'string'],
            'hardware_type' => ['nullable', 'string', 'max:255'],
            'name'          => ['required', 'string', 'max:255'],
            'location'      => ['nullable', 'string', 'max:255'],
            'type'          => ['required', 'string', Rule::in(array_column(SensorType::cases(), 'value'))],
        ];
    }
}
