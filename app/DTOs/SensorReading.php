<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

readonly class SensorReading
{
    /**
     * @param array<string, mixed> $data
     * @param DateTimeImmutable    $recordedAt
     */
    public function __construct(
        public array $data,
        public DateTimeImmutable $recordedAt,
    ) {}
}
