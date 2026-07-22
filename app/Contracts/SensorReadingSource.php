<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\SensorGatewayStatus;
use DateTimeInterface;

interface SensorReadingSource
{
    /**
     * @param string            $mac
     * @param DateTimeInterface $since
     *
     * @return iterable<\App\DTOs\SensorReading>
     */
    public function readingsSince(string $mac, DateTimeInterface $since): iterable;

    /**
     * @return list<\App\DTOs\SensorDevice>
     */
    public function discoverSensors(): array;

    /**
     * @return SensorGatewayStatus
     */
    public function testConnection(): SensorGatewayStatus;
}
