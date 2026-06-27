<?php

declare(strict_types=1);

namespace App\Support;

final class Temperature
{
    private function __construct(public readonly float $celsius) {}

    public static function fromCelsius(float $celsius): self
    {
        return new self($celsius);
    }

    public static function fromDisplay(float $value, string $unit): self
    {
        return new self($unit === 'F' ? ($value - 32) * 5 / 9 : $value);
    }

    public function toDisplay(string $unit): float
    {
        return round($unit === 'F' ? $this->celsius * 9 / 5 + 32 : $this->celsius, 1);
    }
}
