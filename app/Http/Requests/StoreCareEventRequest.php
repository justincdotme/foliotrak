<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CareEventRuleSets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCareEventRequest extends FormRequest
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
        $type = is_string($this->input('type')) ? $this->input('type') : '';

        return [
            'type' => ['required', Rule::in(CareEventRuleSets::TYPES)],
            ...(in_array($type, CareEventRuleSets::TYPES, true) ? CareEventRuleSets::create($type) : []),
        ];
    }
}
