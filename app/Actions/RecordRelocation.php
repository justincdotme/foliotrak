<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for a location change. Both PATCH /plants/{plant} and
 * POST /plants/{plant}/relocations route through here, so the column and the
 * relocation event always move together and an unchanged location writes nothing.
 */
final class RecordRelocation
{
    public function record(
        Plant $plant,
        ?string $toLocation,
        ?Carbon $occurredAt = null,
        ?string $note = null,
        ?int $userId = null,
    ): ?CareEvent {
        if ($toLocation === $plant->location) {
            return null;
        }

        return DB::transaction(function () use ($plant, $toLocation, $occurredAt, $note, $userId): CareEvent {
            $fromLocation = $plant->location;

            $plant->location = $toLocation;
            $plant->save();

            $event = $plant->careEvents()->create([
                'care_event_type_id' => CareEventType::idFor('relocation'),
                'occurred_at' => $occurredAt ?? now(),
                'logged_by_user_id' => $userId,
                'note' => $note,
            ]);

            $event->relocation()->create([
                'from_location' => $fromLocation,
                'to_location' => $toLocation,
            ]);

            return $event;
        });
    }
}
