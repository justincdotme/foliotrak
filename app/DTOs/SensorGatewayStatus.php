<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SensorGatewayStatus
{
    public function __construct(
        public string $status,
        public ?bool $collectorRunning,
        public ?int $sensorsSeen,
        public ?int $uptimeSeconds,
        public ?string $error,
    ) {}
}
