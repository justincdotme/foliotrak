<?php

declare(strict_types=1);

namespace App\Contracts;

interface SensorReadingValue
{
    /**
     * @return array<string, int|float|string|null>
     */
    public function toApiValues(): array;
}
