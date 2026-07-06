<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

readonly class SensorReading
{
    /**
     * @param float             $temperature
     * @param float             $humidity
     * @param DateTimeImmutable $recordedAt
     * @param integer|null      $battery
     * @param integer|null      $rssi
     */
    public function __construct(
        public float $temperature,
        public float $humidity,
        public DateTimeImmutable $recordedAt,
        public ?int $battery,
        public ?int $rssi,
    ) {}
}
