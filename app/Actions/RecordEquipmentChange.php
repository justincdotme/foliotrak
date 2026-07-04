<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Equipment;
use App\Models\Plant;
use App\Support\CareEventSpine;
use Illuminate\Support\Facades\DB;

/**
 * The single writer for a plant's equipment set: it syncs the pivot and records one care event
 * per attached or detached item, so the pivot and the timeline always move together. An unchanged
 * set writes nothing. Deleting an equipment type does NOT pass through here (that is a silent
 * cascade-detach), so bulk type deletion never floods a timeline with removals.
 */
final class RecordEquipmentChange
{
    /**
     * @param  array<int, int|string>  $equipmentIds
     */
    public function record(Plant $plant, array $equipmentIds, ?int $userId = null): void
    {
        DB::transaction(function () use ($plant, $equipmentIds, $userId): void {
            $changes = $plant->equipment()->sync($equipmentIds);

            foreach ($changes['attached'] as $id) {
                $this->recordChange($plant, (int) $id, 'added', $userId);
            }

            foreach ($changes['detached'] as $id) {
                $this->recordChange($plant, (int) $id, 'removed', $userId);
            }
        });
    }

    private function recordChange(Plant $plant, int $equipmentId, string $action, ?int $userId): void
    {
        $event = CareEventSpine::build($plant, 'equipment', null, $userId, null);

        $event->equipmentChange()->create([
            'equipment_id' => $equipmentId,
            'equipment_label' => Equipment::query()->whereKey($equipmentId)->value('label') ?? 'Equipment',
            'action' => $action,
        ]);
    }
}
