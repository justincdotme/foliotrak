<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Derives a plant's care interval from its logged history: the manual override
 * when set, otherwise the median gap between logged events of that type.
 * Reminders, the dashboard, and the condition all read this one rule. It
 * deliberately ignores plant health, since a reminder is a cadence nudge.
 */
final class CareScheduleResolver
{
    /**
     * @param  list<Carbon>  $occurredAt  the event timestamps for one care type
     */
    public static function intervalForType(?int $override, array $occurredAt): ?int
    {
        return $override ?? self::medianGapDays($occurredAt);
    }

    /**
     * @param  list<Carbon>  $occurredAt
     */
    public static function medianGapDays(array $occurredAt): ?int
    {
        if (count($occurredAt) < 2) {
            return null;
        }

        $timestamps = array_map(fn (Carbon $date): int => $date->getTimestamp(), $occurredAt);
        sort($timestamps);

        $gaps = [];
        for ($i = 1; $i < count($timestamps); $i++) {
            $gaps[] = ($timestamps[$i] - $timestamps[$i - 1]) / 86400;
        }

        sort($gaps);
        $middle = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 === 1
            ? $gaps[$middle]
            : ($gaps[$middle - 1] + $gaps[$middle]) / 2;

        // A zero or sub-day median would read as perpetually due; floor at one day.
        return max(1, (int) round($median));
    }
}
