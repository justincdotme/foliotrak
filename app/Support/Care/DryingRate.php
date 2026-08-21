<?php

declare(strict_types=1);

namespace App\Support\Care;

/**
 * An estimated drying rate together with what it rests on, so every surface
 * can state its sample size (ADR-0009).
 */
final readonly class DryingRate
{
    /**
     * @param float   $perDay     Points of the 1-to-10 scale lost per day.
     * @param string  $basis      One of conditioned, humidity_banded, plant_median or cadence_baseline.
     * @param integer $sampleSize Runs behind the estimate; zero for the cadence baseline.
     */
    public function __construct(
        public float $perDay,
        public string $basis,
        public int $sampleSize,
    ) {}
}
