<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SensorReading
{
    public function __construct(
        public float $temperature,
        public float $humidity,
        public \DateTimeImmutable $recordedAt,
        public ?int $battery,
        public ?int $rssi,
    ) {}
}
