<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Location;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (Location::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($value))])->exists()) {
                    $fail('A location with this name already exists.');
                }
            }],
        ];
    }
}
