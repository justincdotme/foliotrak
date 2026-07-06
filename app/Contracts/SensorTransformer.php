<?php

declare(strict_types=1);

namespace App\Contracts;

interface SensorTransformer
{
    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    public function normalize(array $rawData): array;

    /**
     * @param array<string, mixed> $storedData
     *
     * @return SensorReadingValue
     */
    public function hydrate(array $storedData): SensorReadingValue;

    /**
     * @return list<array{key: string, label: string, unit: string, axis: string}>
     */
    public function chartFields(): array;
}
