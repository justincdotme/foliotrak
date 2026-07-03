<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CareEvent;
use App\Support\CareEventRuleSets;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCareEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var CareEvent $event */
        $event = $this->route('event');

        return CareEventRuleSets::update($event->careEventType->key);
    }
}
