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
     * @param  list<array{date: Carbon, from: array{id: int, name: string}|null, to: array{id: int, name: string}|null}>  $relocations  Oldest first.
     * @param  list<array{date: Carbon, health: int}>  $healthObservations  Chronological.
     * @param  array{id: int, name: string}|null  $currentLocation
     * @return list<array{location: array{id: int, name: string}|null, median_health: float|null, sample_size: int, healths: list<int>}>
     */
    public static function forPlant(
        array $relocations,
        array $healthObservations,
        ?array $currentLocation,
    ): array {
        /** @var array<int, array{location: array{id: int, name: string}, healths: list<int>}> $buckets keyed by location ID */
        $buckets = [];
        /** @var list<int> $nullHealths */
        $nullHealths = [];

        foreach ($healthObservations as $obs) {
            $loc = self::locationAt($obs['date'], $relocations, $currentLocation);
            if ($loc === null) {
                $nullHealths[] = $obs['health'];
            } else {
                if (! isset($buckets[$loc['id']])) {
                    $buckets[$loc['id']] = ['location' => $loc, 'healths' => []];
                }
                $buckets[$loc['id']]['healths'][] = $obs['health'];
            }
        }

        /** @var list<array{location: array{id: int, name: string}, median_health: float|null, sample_size: int, healths: list<int>}> $result */
        $result = [];
        foreach ($buckets as $bucket) {
            $result[] = [
                'location' => $bucket['location'],
                'median_health' => Stats::median($bucket['healths']),
                'sample_size' => count($bucket['healths']),
                'healths' => $bucket['healths'],
            ];
        }

        usort(
            $result,
            fn (array $a, array $b): int => $b['sample_size'] <=> $a['sample_size']
            ?: strcmp($a['location']['name'], $b['location']['name'])
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
     * @param  list<array{date: Carbon, from: array{id: int, name: string}|null, to: array{id: int, name: string}|null}>  $relocations
     * @param  array{id: int, name: string}|null  $currentLocation
     * @return array{id: int, name: string}|null
     */
    private static function locationAt(
        Carbon $t,
        array $relocations,
        ?array $currentLocation,
    ): ?array {
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
