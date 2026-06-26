<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use App\Support\Weight;
use Illuminate\Http\Request;

trait WritesCareEvents
{
    protected function newCareEvent(Plant $plant, string $typeKey, Request $request): CareEvent
    {
        return $plant->careEvents()->create([
            'care_event_type_id' => CareEventType::idFor($typeKey),
            'occurred_at' => $request->date('occurred_at'),
            'logged_by_user_id' => $request->user()?->id,
            'note' => $request->filled('note') ? $request->string('note')->value() : null,
        ]);
    }

    /**
     * Sum the lb/oz/g components to canonical grams, treating an absent or empty
     * weight as "not recorded" rather than zero.
     *
     * @param  array<string, mixed>|null  $weight
     */
    protected function gramsFromComponents(?array $weight): ?int
    {
        if (! is_array($weight)) {
            return null;
        }

        $grams = Weight::fromComponents(
            (float) ($weight['lb'] ?? 0),
            (float) ($weight['oz'] ?? 0),
            (float) ($weight['g'] ?? 0),
        )->grams;

        return $grams > 0 ? $grams : null;
    }
}
