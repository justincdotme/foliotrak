<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Carbon;

/**
 * The per-plant "did moving help" signal. For each logged move it summarizes the
 * overall-health readings in the four weeks before and the four weeks after, so a relocation
 * can be read against the plant's own health. Descriptive and non-causal; a move is only
 * reported when there is at least one reading on each side to compare.
 */
final class PositionInsight
{
    private const WINDOW_DAYS = 28;

    /**
     * @param  list<array{date: Carbon, from: array{id: int, name: string}|null, to: array{id: int, name: string}|null}>  $moves
     * @param  list<array{date: Carbon, health: int}>  $healthObservations
     * @return list<array{moved_on: string, from_location: array{id: int, name: string}|null, to_location: array{id: int, name: string}|null, health_before: array{median: float|null, sample_size: int}, health_after: array{median: float|null, sample_size: int}}>
     */
    public static function forMoves(array $moves, array $healthObservations): array
    {
        $insights = [];

        foreach ($moves as $move) {
            $movedAt = $move['date'];
            $before = self::summary(
                $healthObservations,
                fn (Carbon $d): bool => $d >= $movedAt->copy()->subDays(self::WINDOW_DAYS) && $d < $movedAt,
            );
            $after = self::summary(
                $healthObservations,
                fn (Carbon $d): bool => $d > $movedAt && $d <= $movedAt->copy()->addDays(self::WINDOW_DAYS),
            );

            if ($before['sample_size'] === 0 || $after['sample_size'] === 0) {
                continue;
            }

            $insights[] = [
                'moved_on' => $movedAt->format('Y-m-d'),
                'from_location' => $move['from'],
                'to_location' => $move['to'],
                'health_before' => $before,
                'health_after' => $after,
            ];
        }

        return $insights;
    }

    /**
     * @param  list<array{date: Carbon, health: int}>  $observations
     * @param  Closure(Carbon): bool  $within
     * @return array{median: float|null, sample_size: int}
     */
    private static function summary(array $observations, Closure $within): array
    {
        $healths = array_values(array_map(
            fn (array $o): int => $o['health'],
            array_filter($observations, fn (array $o): bool => $within($o['date'])),
        ));

        return ['median' => Stats::median($healths), 'sample_size' => count($healths)];
    }
}
