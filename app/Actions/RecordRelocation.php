<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\CareEventSpine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for a location change. Both PATCH /plants/{plant} (via
 * location_id) and POST /plants/{plant}/care-events (via type: relocation)
 * route through here, so the column and the relocation event always move
 * together and an unchanged location writes nothing.
 */
final class RecordRelocation
{
    /**
     * @param Plant        $plant
     * @param integer|null $toLocationId
     * @param Carbon|null  $occurredAt
     * @param string|null  $note
     * @param integer|null $userId
     *
     * @return CareEvent|null
     */
    public function record(
        Plant $plant,
        ?int $toLocationId,
        ?Carbon $occurredAt = null,
        ?string $note = null,
        ?int $userId = null,
    ): ?CareEvent {
        if ($toLocationId === $plant->location_id) {
            return null;
        }

        return DB::transaction(function () use ($plant, $toLocationId, $occurredAt, $note, $userId): CareEvent {
            $fromLocationId = $plant->location_id;

            $event = CareEventSpine::build($plant, 'relocation', $occurredAt, $userId, $note);

            $event->relocation()->create([
                'from_location_id' => $fromLocationId,
                'to_location_id'   => $toLocationId,
            ]);

            $this->recomputeLocationFromChain($plant);

            return $event;
        });
    }

    /**
     * Derives the plant's current location from the chronologically latest
     * relocation in its care-event chain.
     *
     * @param Plant $plant
     *
     * @return void
     */
    public function recomputeLocationFromChain(Plant $plant): void
    {
        /** @var int|null $locationId */
        $locationId = $plant->careEvents()
            ->whereHas('careEventType', fn ($type) => $type->where('key', 'relocation'))
            ->join('relocation_details', 'care_events.id', '=', 'relocation_details.care_event_id')
            ->orderByDesc('care_events.occurred_at')
            ->orderByDesc('care_events.id')
            ->value('relocation_details.to_location_id');

        $plant->update(['location_id' => $locationId]);
    }
}
