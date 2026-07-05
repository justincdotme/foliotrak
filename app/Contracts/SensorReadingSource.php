<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\SensorDevice;
use App\DTOs\SensorGatewayStatus;
use App\DTOs\SensorReading;

interface SensorReadingSource
{
    /** @return iterable<SensorReading> */
    public function readingsSince(string $mac, \DateTimeInterface $since): iterable;

    /** @return list<SensorDevice> */
    public function discoverSensors(): array;

    public function testConnection(): SensorGatewayStatus;
}
