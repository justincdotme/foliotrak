<?php

declare(strict_types=1);

namespace App\Support\Care;

/**
 * One observed stretch of drying: how fast the soil fell, and the ambient
 * conditions it fell in.
 */
final readonly class DryingRun
{
    /**
     * @param float      $perDay      Points of the 1-to-10 scale lost per day, always positive.
     * @param float|null $humidityPct Mean over the run.
     * @param float|null $tempC       Mean over the run.
     */
    public function __construct(
        public float $perDay,
        public ?float $humidityPct = null,
        public ?float $tempC = null,
    ) {}
}
