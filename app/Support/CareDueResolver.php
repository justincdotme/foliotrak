<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CareDueResolver
{
    /**
     * The care-due entries for a plant, one per care type that has a derivable
     * interval (the manual override, else the median of logged gaps) and an
     * anchor to count from (the last event, else the schedule start date).
     *
     * @return list<array{plant_id: int, common_name: string|null, scientific_name: string|null, status: string, due_date: string, type: string, daysLeft: int, interval: int}>
     */
    public static function forPlant(Plant $plant): array
    {
        return array_values(array_filter([
            self::entry($plant, 'watering', $plant->watering_interval_days_override, $plant->wateringEvents, $plant->watering_schedule_start_date),
            self::entry($plant, 'fertilizing', $plant->fertilizing_interval_days_override, $plant->fertilizingEvents),
        ]));
    }

    public static function status(int $daysLeft): string
    {
        if ($daysLeft < 0) {
            return 'overdue';
        }

        if ($daysLeft <= 1) {
            return 'due-soon';
        }

        return 'ok';
    }

    /**
     * @param  Collection<int, CareEvent>  $events  every logged event of the type, oldest first
     * @return array{plant_id: int, common_name: string|null, scientific_name: string|null, status: string, due_date: string, type: string, daysLeft: int, interval: int}|null
     */
    private static function entry(Plant $plant, string $type, ?int $override, Collection $events, ?Carbon $startDate = null): ?array
    {
        $interval = CareScheduleResolver::intervalForType(
            $override,
            $events->map(fn (CareEvent $event): Carbon => $event->occurred_at)->all(),
        );

        if ($interval === null) {
            return null;
        }

        $anchor = $events->isEmpty() ? $startDate : $events->last()->occurred_at;

        if ($anchor === null) {
            return null;
        }

        $due = $anchor->copy()->addDays($interval);
        $daysLeft = (int) round(($due->timestamp - now()->timestamp) / 86400);

        return [
            'plant_id' => $plant->id,
            'common_name' => $plant->common_name,
            'scientific_name' => $plant->scientific_name,
            'status' => self::status($daysLeft),
            'due_date' => $due->format('Y-m-d'),
            'type' => $type,
            'daysLeft' => $daysLeft,
            'interval' => $interval,
        ];
    }
}
