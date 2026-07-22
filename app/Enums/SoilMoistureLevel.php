<?php

declare(strict_types=1);

namespace App\Enums;

enum SoilMoistureLevel: string
{
    /** Dry soil */
    case Dry = 'dry';

    /** Moist soil */
    case Moist = 'moist';

    /** Wet soil */
    case Wet = 'wet';

    /**
     * Position of the relative reading on the 1-to-10 precise scale, so the
     * correlation factor and the watering recommender agree on what a
     * qualitative reading means.
     *
     * @return float
     */
    public function numericValue(): float
    {
        return match ($this) {
            self::Dry   => 2.0,
            self::Moist => 5.0,
            self::Wet   => 8.0,
        };
    }
}
