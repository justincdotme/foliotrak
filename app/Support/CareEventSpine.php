<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use Carbon\CarbonInterface;

final class CareEventSpine
{
    /**
     * A missing occurred_at defaults to now so the typed creates and the
     * relocation action anchor the spine identically.
     */
    public static function build(
        Plant $plant,
        string $typeKey,
        CarbonInterface|string|null $occurredAt,
        ?int $userId,
        ?string $note,
    ): CareEvent {
        return $plant->careEvents()->create([
            'care_event_type_id' => CareEventType::idFor($typeKey),
            'occurred_at' => $occurredAt ?: now(),
            'logged_by_user_id' => $userId,
            'note' => $note,
        ]);
    }
}
