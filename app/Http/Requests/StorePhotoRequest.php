<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            // A documented photo links to one of this plant's care events; the
            // plant scope keeps a photo from pointing at another plant's event.
            'care_event_id' => [
                'nullable',
                'integer',
                Rule::exists('care_events', 'id')->where('plant_id', $this->route('plant')->id),
            ],
        ];
    }
}
