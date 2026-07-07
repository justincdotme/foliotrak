<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\SensorTransformer;
use App\Services\Sensors\Transformers\HygrometerTransformer;

enum SensorType: string
{
    case Hygrometer = 'hygrometer';

    /**
     * Maps the gateway's hardware identity to the semantic type it measures,
     * so registration can preselect the right type for known devices.
     *
     * @param string|null $hardwareType
     *
     * @return self|null
     */
    public static function forHardware(?string $hardwareType): ?self
    {
        return match ($hardwareType) {
            'govee_h5075' => self::Hygrometer,
            default       => null,
        };
    }

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
