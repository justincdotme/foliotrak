<?php

declare(strict_types=1);

namespace App\Support\Care;

use Illuminate\Support\Carbon;

/**
 * One soil reading treated as a verdict on the watering interval that
 * preceded it, together with the air it was taken in.
 */
final readonly class SoilEvidence
{
    /**
     * @param float      $daysSinceWatering Days between the previous watering and this reading.
     * @param float      $value             Scale position, 1 driest to 10 wettest.
     * @param Carbon     $at
     * @param string     $source            Either `observation` or `sensor`.
     * @param float|null $humidityPct
     * @param float|null $tempC
     */
    public function __construct(
        public float $daysSinceWatering,
        public float $value,
        public Carbon $at,
        public string $source,
        public ?float $humidityPct = null,
        public ?float $tempC = null,
    ) {}

    /**
     * A reading at or below the watering threshold says the plant was ready
     * by this day; anything above it says only that it was not ready yet.
     *
     * @return boolean
     */
    public function reachedWateringLevel(): bool
    {
        return $this->value <= DryingRateEstimator::WATER_AT;
    }
}
