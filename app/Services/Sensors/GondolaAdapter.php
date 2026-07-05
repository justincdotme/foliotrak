<?php

declare(strict_types=1);

namespace App\Services\Sensors;

use App\Contracts\SensorReadingSource;
use App\DTOs\SensorDevice;
use App\DTOs\SensorGatewayStatus;
use App\DTOs\SensorReading;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class GondolaAdapter implements SensorReadingSource
{
    /** @return \Generator<int, SensorReading> */
    public function readingsSince(string $mac, \DateTimeInterface $since): \Generator
    {
        if (! $this->isConfigured()) {
            return;
        }

        $from = $since->format('Y-m-d\TH:i:s\Z');

        do {
            try {
                $response = $this->client()
                    ->get($this->url('/api/v1/readings'), [
                        'mac' => $mac,
                        'from' => $from,
                    ]);
            } catch (ConnectionException) {
                return;
            }

            if ($response->status() === 404) {
                return;
            }

            if (! $response->successful()) {
                return;
            }

            $body = $response->json();
            $readings = $body['readings'] ?? [];

            // An empty page cannot advance the cursor; stop rather than trust has_more.
            if ($readings === []) {
                return;
            }

            foreach ($readings as $row) {
                $recordedAt = new \DateTimeImmutable($row['recorded_at']);
                $from = $recordedAt->format('Y-m-d\TH:i:s\Z');

                yield new SensorReading(
                    temperature: (float) $row['temperature'],
                    humidity: (float) $row['humidity'],
                    recordedAt: $recordedAt,
                    battery: isset($row['battery']) ? (int) $row['battery'] : null,
                    rssi: isset($row['rssi']) ? (int) $row['rssi'] : null,
                );
            }

            $hasMore = $body['has_more'] ?? false;
        } while ($hasMore);
    }

    /** @return list<SensorDevice> */
    public function discoverSensors(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client()->get($this->url('/api/v1/sensors'));
        } catch (ConnectionException) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        $sensors = [];
        foreach ($response->json('sensors', []) as $entry) {
            $lastReading = null;
            if (isset($entry['last_reading'])) {
                $lr = $entry['last_reading'];
                $lastReading = new SensorReading(
                    temperature: (float) $lr['temperature'],
                    humidity: (float) $lr['humidity'],
                    recordedAt: new \DateTimeImmutable($lr['recorded_at']),
                    battery: isset($lr['battery']) ? (int) $lr['battery'] : null,
                    rssi: isset($lr['rssi']) ? (int) $lr['rssi'] : null,
                );
            }

            $sensors[] = new SensorDevice(
                mac: $entry['mac'],
                deviceName: $entry['device_name'] ?? '',
                lastReading: $lastReading,
            );
        }

        return $sensors;
    }

    public function testConnection(): SensorGatewayStatus
    {
        if (! $this->isConfigured()) {
            return new SensorGatewayStatus(
                status: 'not_configured',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Gateway URL or API key not set',
            );
        }

        try {
            $healthResponse = Http::withOptions(['verify' => config('sensors.verify')])
                ->get($this->url('/api/v1/health'));
        } catch (ConnectionException $e) {
            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: $e->getMessage(),
            );
        }

        if (! $healthResponse->successful()) {
            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Health endpoint returned '.$healthResponse->status(),
            );
        }

        try {
            $sensorsResponse = $this->client()->get($this->url('/api/v1/sensors'));
        } catch (ConnectionException $e) {
            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: $e->getMessage(),
            );
        }

        if ($sensorsResponse->status() === 401) {
            return new SensorGatewayStatus(
                status: 'auth_failed',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Invalid API key',
            );
        }

        $health = $healthResponse->json();

        return new SensorGatewayStatus(
            status: 'connected',
            collectorRunning: $health['collector_running'] ?? null,
            sensorsSeen: $health['sensors_seen'] ?? null,
            uptimeSeconds: $health['uptime_seconds'] ?? null,
            error: null,
        );
    }

    private function isConfigured(): bool
    {
        return config('sensors.base_url') !== ''
            && config('sensors.api_key') !== '';
    }

    private function url(string $path): string
    {
        return rtrim(config('sensors.base_url'), '/').$path;
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders(['X-API-Key' => config('sensors.api_key')])
            ->withOptions(['verify' => config('sensors.verify')]);
    }
}
