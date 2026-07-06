<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class SensorGatewayStatus
{
    /**
     * @param string       $status
     * @param boolean|null $collectorRunning
     * @param integer|null $sensorsSeen
     * @param integer|null $uptimeSeconds
     * @param string|null  $error
     */
    public function __construct(
        public string $status,
        public ?bool $collectorRunning,
        public ?int $sensorsSeen,
        public ?int $uptimeSeconds,
        public ?string $error,
    ) {}
}
