<?php

declare(strict_types=1);

namespace App\Support\Care;

use Illuminate\Support\Carbon;

/**
 * One soil moisture reading on the 1-to-10 scale, carrying the ambient
 * conditions recorded alongside it so a drying run can be attributed to the
 * conditions it happened in.
 */
final readonly class SoilReading
{
    /**
     * @param float      $value       Scale position, 1 driest to 10 wettest.
     * @param Carbon     $at
     * @param string     $source      Either `observation` or `sensor`.
     * @param float|null $humidityPct
     * @param float|null $tempC
     */
    public function __construct(
        public float $value,
        public Carbon $at,
        public string $source,
        public ?float $humidityPct = null,
        public ?float $tempC = null,
    ) {}
}
