<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\SensorTransformer;
use App\Services\Sensors\Transformers\HygrometerTransformer;

enum SensorType: string
{
    case Hygrometer = 'hygrometer';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Hygrometer => 'Hygrometer',
        };
    }

    /**
     * @return SensorTransformer
     */
    public function transformer(): SensorTransformer
    {
        return match ($this) {
            self::Hygrometer => new HygrometerTransformer,
        };
    }
}
