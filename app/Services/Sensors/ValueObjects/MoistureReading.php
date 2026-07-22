<?php

declare(strict_types=1);

namespace App\Services\Sensors\ValueObjects;

use App\Contracts\SensorReadingValue;

final readonly class MoistureReading implements SensorReadingValue
{
    /**
     * @param integer      $moisture
     * @param integer|null $battery
     * @param integer|null $rssi
     */
    public function __construct(
        public int $moisture,
        public ?int $battery,
        public ?int $rssi,
    ) {}

    /**
     * @return array<string, int|float|string|null>
     */
    public function toApiValues(): array
    {
        return [
            'moisture' => $this->moisture,
        ];
    }
}
