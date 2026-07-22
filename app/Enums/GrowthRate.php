<?php

declare(strict_types=1);

namespace App\Enums;

enum GrowthRate: string
{
    case None     = 'none';
    case Slow     = 'slow';
    case Moderate = 'moderate';
    case Fast     = 'fast';
}
