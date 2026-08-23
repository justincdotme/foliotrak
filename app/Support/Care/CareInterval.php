<?php

declare(strict_types=1);

namespace App\Support\Care;

/**
 * A proposed interval between care events together with what it rests on, so
 * every surface can state its sample size (ADR-0009).
 */
final readonly class CareInterval
{
    /**
     * @param integer      $days        The interval the next due date counts from.
     * @param string       $basis       One of conditioned, humidity_banded, temperature_banded, plant_soil, override or cadence.
     * @param integer      $sampleSize  Soil readings behind the estimate; zero when none informed it.
     * @param integer      $cadenceDays The logged median gap, before any soil evidence.
     * @param integer|null $learnedDays What the soil evidence alone implies, null when there is none.
     */
    public function __construct(
        public int $days,
        public string $basis,
        public int $sampleSize,
        public int $cadenceDays,
        public ?int $learnedDays = null,
    ) {}

    /**
     * @param integer $days
     * @param string  $basis
     *
     * @return self
     */
    public static function fromCadence(int $days, string $basis = 'cadence'): self
    {
        return new self($days, $basis, 0, $days);
    }
}
