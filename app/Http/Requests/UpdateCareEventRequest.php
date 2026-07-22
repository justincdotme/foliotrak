<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CareEventRuleSets;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCareEventRequest extends FormRequest
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
        /** @var CareEvent $event */
        $event = $this->route('event');

        return CareEventRuleSets::update($event->careEventType->key);
    }
}
