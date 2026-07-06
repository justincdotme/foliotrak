<?php

declare(strict_types=1);

namespace App\Services\Sensors\Transformers;

use App\Contracts\SensorTransformer;
use App\Services\Sensors\ValueObjects\HygrometerReading;

final class HygrometerTransformer implements SensorTransformer
{
    /**
     * @param array<string, mixed> $rawData
     *
     * @return array<string, mixed>
     */
    public function normalize(array $rawData): array
    {
        return array_filter([
            'temperature' => (float) $rawData['temperature'],
            'humidity'    => (float) $rawData['humidity'],
            'battery'     => isset($rawData['battery']) ? (int) $rawData['battery'] : null,
            'rssi'        => isset($rawData['rssi']) ? (int) $rawData['rssi'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * @param array<string, mixed> $storedData
     *
     * @return HygrometerReading
     */
    public function hydrate(array $storedData): HygrometerReading
    {
        return new HygrometerReading(
            temperature: (float) $storedData['temperature'],
            humidity: (float) $storedData['humidity'],
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
            ['key' => 'temperature_f', 'label' => 'Temperature', 'unit' => "\u{00B0}F", 'axis' => 'left'],
            ['key' => 'humidity', 'label' => 'Humidity', 'unit' => '%', 'axis' => 'right'],
        ];
    }
}
