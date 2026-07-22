<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSensorCalibrationRequest extends FormRequest
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
            'points'            => ['present', 'array', 'max:10'],
            'points.*.position' => ['required', 'integer', 'between:1,10', 'distinct'],
            'points.*.value'    => ['required', 'integer', 'between:0,4095'],
        ];
    }
}
