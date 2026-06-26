<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CareEvent;
use App\Models\Plant;

class CareDueResolver
{
    /**
     * The override-driven care-due entries for a plant, one per care type that has
     * both a manual interval override and a logged event to count from. The derived
     * (median) interval lands with reminders in a later phase, so a plant without an
     * override is not yet "due" here.
     *
     * @return list<array{plant_id: int, common_name: string|null, scientific_name: string|null, status: string, due_date: string, type: string, daysLeft: int, interval: int}>
     */
    public static function forPlant(Plant $plant): array
    {
        return array_values(array_filter([
            self::entry($plant, 'watering', $plant->watering_interval_days_override, $plant->latestWateringEvent),
            self::entry($plant, 'fertilizing', $plant->fertilizing_interval_days_override, $plant->latestFertilizingEvent),
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
     * @return array{plant_id: int, common_name: string|null, scientific_name: string|null, status: string, due_date: string, type: string, daysLeft: int, interval: int}|null
     */
    private static function entry(Plant $plant, string $type, ?int $interval, ?CareEvent $lastEvent): ?array
    {
        if ($interval === null || $lastEvent === null) {
            return null;
        }

        $due = $lastEvent->occurred_at->copy()->addDays($interval);
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
