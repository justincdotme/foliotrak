<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Groups a plant's health observations by the location it occupied at the time of each
 * reading. Uses the relocation chain to reconstruct historical positions without hitting
 * the database.
 */
final class LocationHealthInsight
{
    /**
     * @param  list<array{date: Carbon, from: string|null, to: string|null}>  $relocations  Oldest first.
     * @param  list<array{date: Carbon, health: int}>  $healthObservations  Chronological.
     * @return list<array{location: string|null, median_health: float|null, sample_size: int, healths: list<int>}>
     */
    public static function forPlant(
        array $relocations,
        array $healthObservations,
        ?string $currentLocation,
    ): array {
        /** @var array<string, list<int>> $nonNullBuckets */
        $nonNullBuckets = [];
        /** @var list<int> $nullHealths */
        $nullHealths = [];

        foreach ($healthObservations as $obs) {
            $location = self::locationAt($obs['date'], $relocations, $currentLocation);
            if ($location === null) {
                $nullHealths[] = $obs['health'];
            } else {
                if (! isset($nonNullBuckets[$location])) {
                    $nonNullBuckets[$location] = [];
                }
                $nonNullBuckets[$location][] = $obs['health'];
            }
        }

        /** @var list<array{location: string, median_health: float|null, sample_size: int, healths: list<int>}> $result */
        $result = [];
        foreach ($nonNullBuckets as $location => $healths) {
            $result[] = [
                'location' => $location,
                'median_health' => Stats::median($healths),
                'sample_size' => count($healths),
                'healths' => $healths,
            ];
        }

        usort(
            $result,
            fn (array $a, array $b): int => $b['sample_size'] <=> $a['sample_size']
            ?: strcmp($a['location'], $b['location'])
        );

        if ($nullHealths !== []) {
            $result[] = [
                'location' => null,
                'median_health' => Stats::median($nullHealths),
                'sample_size' => count($nullHealths),
                'healths' => $nullHealths,
            ];
        }

        return $result;
    }

    /**
     * Resolves the plant's location at time $t using its relocation chain.
     *
     * @param  list<array{date: Carbon, from: string|null, to: string|null}>  $relocations
     */
    private static function locationAt(
        Carbon $t,
        array $relocations,
        ?string $currentLocation,
    ): ?string {
        if ($relocations === []) {
            return $currentLocation;
        }

        if ($t < $relocations[0]['date']) {
            return $relocations[0]['from'];
        }

        $location = null;
        foreach ($relocations as $relocation) {
            if ($relocation['date']->lte($t)) {
                $location = $relocation['to'];
            }
        }

        return $location;
    }
}
