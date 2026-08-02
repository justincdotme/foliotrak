<?php

declare(strict_types=1);

namespace App\Services\Sensors;

use App\Contracts\SensorReadingSource;
use App\DTOs\SensorDevice;
use App\DTOs\SensorGatewayStatus;
use App\DTOs\SensorReading;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GondolaAdapter implements SensorReadingSource
{
    /**
     * @param string            $mac
     * @param DateTimeInterface $since
     *
     * @return Generator<int, SensorReading>
     */
    public function readingsSince(string $mac, DateTimeInterface $since): Generator
    {
        if (! $this->isConfigured()) {
            return;
        }

        $from = $since->format('Y-m-d\TH:i:s\Z');

        do {
            try {
                $response = $this->client('GET', '/api/v1/readings')
                    ->get($this->url('/api/v1/readings'), [
                        'mac'  => $mac,
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

            $body     = $response->json();
            $readings = $body['readings'] ?? [];

            // An empty page cannot advance the cursor; stop rather than trust has_more.
            if ($readings === []) {
                return;
            }

            foreach ($readings as $row) {
                $recordedAt = new DateTimeImmutable($row['recorded_at']);
                $from       = $recordedAt->format('Y-m-d\TH:i:s\Z');

                yield new SensorReading(
                    data: $this->readingData($row),
                    recordedAt: $recordedAt,
                );
            }

            $hasMore = $body['has_more'] ?? false;
        } while ($hasMore);
    }

    /**
     * @return list<SensorDevice>
     */
    public function discoverSensors(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = $this->client('GET', '/api/v1/sensors')->get($this->url('/api/v1/sensors'));
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
                    data: $this->readingData($lr),
                    recordedAt: new DateTimeImmutable($lr['recorded_at']),
                );
            }

            $sensors[] = new SensorDevice(
                mac: $entry['mac'],
                deviceName: $entry['device_name'] ?? '',
                lastReading: $lastReading,
                sensorType: $entry['sensor_type'] ?? null,
            );
        }

        return $sensors;
    }

    /**
     * @return SensorGatewayStatus
     */
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
            Log::error('Gondola health check failed', ['exception' => $e]);

            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Unable to reach sensor gateway',
            );
        }

        if (! $healthResponse->successful()) {
            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Health endpoint returned ' . $healthResponse->status(),
            );
        }

        try {
            $sensorsResponse = $this->client('GET', '/api/v1/sensors')->get($this->url('/api/v1/sensors'));
        } catch (ConnectionException $e) {
            Log::error('Gondola sensor list failed', ['exception' => $e]);

            return new SensorGatewayStatus(
                status: 'unreachable',
                collectorRunning: null,
                sensorsSeen: null,
                uptimeSeconds: null,
                error: 'Unable to reach sensor gateway',
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

    /**
     * Accepts both gateway response generations: the original flat shape, and
     * the current one where values nest under a measurements dict beside a
     * per-row sensor_type.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function readingData(array $row): array
    {
        unset($row['recorded_at'], $row['sensor_type']);

        $measurements = $row['measurements'] ?? null;

        if (is_array($measurements)) {
            unset($row['measurements']);

            return array_merge($row, $measurements);
        }

        return $row;
    }

    /**
     * @return boolean
     */
    private function isConfigured(): bool
    {
        return config('sensors.base_url') !== ''
            && config('sensors.api_key') !== '';
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function url(string $path): string
    {
        return rtrim(config('sensors.base_url'), '/') . $path;
    }

    /**
     * @param string $method
     * @param string $path
     *
     * @return array<string, string>
     */
    private function hmacHeaders(string $method, string $path): array
    {
        $timestamp = (string) time();
        $canonical = "{$method}\n{$path}\n{$timestamp}";
        $signature = hash_hmac('sha256', $canonical, config('sensors.api_key'));

        return [
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ];
    }

    /**
     * @param string $method
     * @param string $path
     *
     * @return PendingRequest
     */
    private function client(string $method = 'GET', string $path = '/'): PendingRequest
    {
        return Http::withHeaders($this->hmacHeaders($method, $path))
            ->withOptions(['verify' => config('sensors.verify')]);
    }
}
