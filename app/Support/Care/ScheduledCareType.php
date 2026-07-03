<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Models\CareEvent;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * The care types a plant can be scheduled for. Each knows where its override,
 * logged history, and optional start-date anchor live on the plant, so due
 * consumers iterate the cases instead of hardcoding the pair.
 */
enum ScheduledCareType: string
{
    case Watering = 'watering';
    case Fertilizing = 'fertilizing';

    public function override(Plant $plant): ?int
    {
        return match ($this) {
            self::Watering => $plant->watering_interval_days_override,
            self::Fertilizing => $plant->fertilizing_interval_days_override,
        };
    }

    /**
     * @return Collection<int, CareEvent> every logged event of the type, oldest first
     */
    public function events(Plant $plant): Collection
    {
        return match ($this) {
            self::Watering => $plant->wateringEvents,
            self::Fertilizing => $plant->fertilizingEvents,
        };
    }

    public function scheduleStartDate(Plant $plant): ?Carbon
    {
        return match ($this) {
            self::Watering => $plant->watering_schedule_start_date,
            self::Fertilizing => null,
        };
    }
}
