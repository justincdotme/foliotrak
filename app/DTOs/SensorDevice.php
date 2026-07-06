<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SensorDevice
{
    /**
     * @param string             $mac
     * @param string             $deviceName
     * @param SensorReading|null $lastReading
     */
    public function __construct(
        public string $mac,
        public string $deviceName,
        public ?SensorReading $lastReading,
    ) {}
}
