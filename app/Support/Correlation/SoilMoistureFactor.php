<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Enums\SoilMoistureLevel;
use App\Models\Plant;
use Illuminate\Support\Collection;

/**
 * Soil moisture against observed health. Uses soil_moisture_precise (1 to 10 int) when recorded;
 * falls back to `SoilMoistureLevel::numericValue()` when the precise field is absent. Pairs with
 * overall_health; skips when both moisture values are null or health is null. Pooled across the
 * plant group.
 */
final class SoilMoistureFactor implements Factor
{
    public function key(): string
    {
        return 'soil_moisture';
    }

    public function outcomeKey(): string
    {
        return 'overall_health';
    }

    /**
     * @return list<string>
     */
    public function relations(): array
    {
        return ['observationEvents.observation'];
    }

    /**
     * @param  Collection<int, Plant>  $plants
     * @return list<array{x: float, y: float}>
     */
    public function pairs(Collection $plants): array
    {
        $pairs = [];

        foreach ($plants as $plant) {
            foreach ($plant->observationEvents as $event) {
                $obs = $event->observation;
                $health = $obs?->overall_health;

                if ($health === null) {
                    continue;
                }

                $moisture = $this->resolveMoisture($obs->soil_moisture_precise, $obs->soil_moisture_relative);

                if ($moisture === null) {
                    continue;
                }

                $pairs[] = ['x' => $moisture, 'y' => (float) $health];
            }
        }

        return $pairs;
    }

    private function resolveMoisture(?int $precise, ?SoilMoistureLevel $relative): ?float
    {
        if ($precise !== null) {
            return (float) $precise;
        }

        return $relative?->numericValue();
    }
}
