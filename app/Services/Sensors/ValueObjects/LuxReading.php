<?php

declare(strict_types=1);

namespace App\Services\Sensors\ValueObjects;

use App\Contracts\SensorReadingValue;

final readonly class LuxReading implements SensorReadingValue
{
    /**
     * @param float        $lux
     * @param integer      $white
     * @param integer      $als
     * @param integer|null $rssi
     */
    public function __construct(
        public float $lux,
        public int $white,
        public int $als,
        public ?int $rssi,
    ) {}

    /**
     * @return array<string, int|float|string|null>
     */
    public function toApiValues(): array
    {
        return [
            'lux' => round($this->lux, 1),
        ];
    }
}
