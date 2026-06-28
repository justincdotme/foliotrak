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
            'photo' => ['required', File::image()->max(25 * 1024)],
            'taken_on' => ['nullable', 'date'],
            'caption' => ['nullable', 'string', 'max:255'],
            'set_as_cover' => ['nullable', 'boolean'],
            'care_event_id' => [
                'nullable',
                'integer',
                Rule::exists('care_events', 'id')->where('plant_id', $this->route('plant')->id),
            ],
            'hero_crop' => ['nullable', 'json', 'required_with:thumb_crop'],
            'thumb_crop' => ['nullable', 'json', 'required_with:hero_crop'],
        ];
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public function heroCrop(): ?array
    {
        return $this->decodeCrop('hero_crop');
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    public function thumbCrop(): ?array
    {
        return $this->decodeCrop('thumb_crop');
    }

    /**
     * @return array{x: int, y: int, width: int, height: int}|null
     */
    private function decodeCrop(string $field): ?array
    {
        $json = $this->input($field);
        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return null;
        }

        return [
            'x' => max(0, (int) ($data['x'] ?? 0)),
            'y' => max(0, (int) ($data['y'] ?? 0)),
            'width' => max(1, (int) ($data['width'] ?? 1)),
            'height' => max(1, (int) ($data['height'] ?? 1)),
        ];
    }
}
