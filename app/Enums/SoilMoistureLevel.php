<?php

declare(strict_types=1);

namespace App\Enums;

enum SoilMoistureLevel: string
{
    case Dry = 'dry';
    case Moist = 'moist';
    case Wet = 'wet';
}
