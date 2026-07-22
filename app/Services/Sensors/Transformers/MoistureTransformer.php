<?php

declare(strict_types=1);

namespace App\Services\Sensors\Transformers;

use App\Contracts\SensorTransformer;
use App\Services\Sensors\ValueObjects\MoistureReading;
use InvalidArgumentException;

final class MoistureTransformer implements SensorTransformer
{
    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    public function normalize(array $rawData): array
    {
        if (! isset($rawData['moisture'])) {
            throw new InvalidArgumentException('Moisture reading requires a moisture key.');
        }

        return array_filter([
            'moisture' => (int) $rawData['moisture'],
            'battery'  => isset($rawData['battery']) ? (int) $rawData['battery'] : null,
            'rssi'     => isset($rawData['rssi']) ? (int) $rawData['rssi'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param array<string, mixed> $storedData
     *
     * @return MoistureReading
     */
    public function hydrate(array $storedData): MoistureReading
    {
        return new MoistureReading(
            moisture: (int) $storedData['moisture'],
            battery: isset($storedData['battery']) ? (int) $storedData['battery'] : null,
            rssi: isset($storedData['rssi']) ? (int) $storedData['rssi'] : null,
        );
    }

    /**
     * @return list<array{key: string, label: string, unit: string, axis: string}>
     */
    public function chartFields(): array
    {
        return [
            ['key' => 'moisture', 'label' => 'Soil Moisture', 'unit' => '', 'axis' => 'moisture'],
        ];
    }
}
