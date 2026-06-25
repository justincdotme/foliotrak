<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePhotoRequest extends FormRequest
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
            'photo' => ['required', File::image()->max(12 * 1024)],
            'taken_on' => ['nullable', 'date'],
            'caption' => ['nullable', 'string', 'max:255'],
            'set_as_cover' => ['nullable', 'boolean'],
        ];
    }
}
