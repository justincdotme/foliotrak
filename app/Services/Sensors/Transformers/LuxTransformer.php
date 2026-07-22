<?php

declare(strict_types=1);

namespace App\Services\Sensors\Transformers;

use App\Contracts\SensorTransformer;
use App\Services\Sensors\ValueObjects\LuxReading;
use InvalidArgumentException;

final class LuxTransformer implements SensorTransformer
{
    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    public function normalize(array $rawData): array
    {
        if (! isset($rawData['lux'])) {
            throw new InvalidArgumentException('Lux reading requires a lux key.');
        }

        return array_filter([
            'lux'   => (float) $rawData['lux'],
            'white' => isset($rawData['white']) ? (int) $rawData['white'] : null,
            'als'   => isset($rawData['als']) ? (int) $rawData['als'] : null,
            'rssi'  => isset($rawData['rssi']) ? (int) $rawData['rssi'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param array<string, mixed> $storedData
     *
     * @return LuxReading
     */
    public function hydrate(array $storedData): LuxReading
    {
        return new LuxReading(
            lux: (float) $storedData['lux'],
            white: isset($storedData['white']) ? (int) $storedData['white'] : 0,
            als: isset($storedData['als']) ? (int) $storedData['als'] : 0,
            rssi: isset($storedData['rssi']) ? (int) $storedData['rssi'] : null,
        );
    }

    /**
     * @return list<array{key: string, label: string, unit: string, axis: string}>
     */
    public function chartFields(): array
    {
        return [
            ['key' => 'lux', 'label' => 'Light', 'unit' => ' lx', 'axis' => 'lux'],
        ];
    }
}
