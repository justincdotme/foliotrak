<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
        // Pushover keys are exactly 30 alphanumeric characters; the channel rejects
        // anything else, so reject it here rather than failing in the queued job.
        return [
            'pushover_user_key' => ['sometimes', 'nullable', 'string', 'size:30', 'regex:/^[A-Za-z0-9]+$/'],
        ];
    }
}
