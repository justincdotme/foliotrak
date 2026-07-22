<?php

declare(strict_types=1);

namespace App\Services\Sensors\ValueObjects;

use App\Contracts\SensorReadingValue;

final readonly class HygrometerReading implements SensorReadingValue
{
    /**
     * @param float        $temperature
     * @param float        $humidity
     * @param integer|null $battery
     * @param integer|null $rssi
     */
    public function __construct(
        public float $temperature,
        public float $humidity,
        public ?int $battery,
        public ?int $rssi,
    ) {}

    /**
     * @return array<string, int|float|string|null>
     */
    public function toApiValues(): array
    {
        return [
            'temperature_f' => round($this->temperature * 9 / 5 + 32, 1),
            'humidity'      => $this->humidity,
        ];
    }
}
