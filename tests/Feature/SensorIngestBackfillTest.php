<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sensor;
use App\Models\SensorReading;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SensorIngestBackfillTest extends TestCase
{
    use RefreshDatabase;

    private const GATEWAY_URL = 'https://gateway.test';

    private const API_KEY = 'test-key-not-real';

    private const MAC = 'AA:BB:CC:DD:EE:FF';

    /** @var list<array{temperature: float, humidity: float, battery: int, rssi: int, recorded_at: string}> */
    private array $dataset = [];

    private Carbon $startTime;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sensors.base_url' => self::GATEWAY_URL,
            'sensors.api_key' => self::API_KEY,
        ]);

        $this->startTime = Carbon::now('UTC')->subHours(5);

        for ($i = 0; $i < 250; $i++) {
            $this->dataset[] = [
                'temperature' => round(20.0 + $i * 0.1, 1),
                'humidity' => round(50.0 + $i * 0.05, 2),
                'battery' => 95,
                'rssi' => -60,
                'recorded_at' => $this->startTime->copy()->addSeconds($i * 60)->format('Y-m-d\TH:i:s\Z'),
            ];
        }
    }

    public function test_multi_page_backfill_produces_gap_free_series(): void
    {
        $this->fakeGondolaHealthy();
        Sensor::create([
            'mac' => self::MAC,
            'device_name' => 'Test Sensor',
            'name' => 'Test',
            'color' => 'var(--series-1)',
        ]);

        $this->artisan('sensors:ingest')->assertExitCode(0);

        $storedTimestamps = SensorReading::query()
            ->pluck('recorded_at')
            ->map(fn ($ts) => Carbon::parse($ts)->format('Y-m-d\TH:i:s\Z'))
            ->sort()
            ->values()
            ->all();

        $expectedTimestamps = array_column($this->dataset, 'recorded_at');
        sort($expectedTimestamps);

        $this->assertCount(250, $storedTimestamps);
        $this->assertSame($expectedTimestamps, $storedTimestamps);

        // The retired newest-first contract orphaned holes exactly at page boundaries.
        $this->assertContains($this->dataset[99]['recorded_at'], $storedTimestamps);
        $this->assertContains($this->dataset[100]['recorded_at'], $storedTimestamps);
        $this->assertContains($this->dataset[199]['recorded_at'], $storedTimestamps);
        $this->assertContains($this->dataset[200]['recorded_at'], $storedTimestamps);
    }

    public function test_interrupted_backfill_resumes_without_gaps_or_duplicates(): void
    {
        $sensor = Sensor::create([
            'mac' => self::MAC,
            'device_name' => 'Test Sensor',
            'name' => 'Test',
            'color' => 'var(--series-1)',
        ]);

        // Throws only on request #2 (the second page of the first run)
        $requestCount = 0;
        Http::fake(function (Request $request) use (&$requestCount) {
            if (! str_contains($request->url(), '/api/v1/readings')) {
                return Http::response('', 404);
            }

            $requestCount++;
            if ($requestCount === 2) {
                throw new ConnectionException('Simulated outage');
            }

            return $this->gondolaResponse($request);
        });

        $this->artisan('sensors:ingest')->assertExitCode(0);

        $this->assertSame(100, SensorReading::where('sensor_id', $sensor->id)->count());

        $this->artisan('sensors:ingest')->assertExitCode(0);

        $storedTimestamps = SensorReading::query()
            ->where('sensor_id', $sensor->id)
            ->pluck('recorded_at')
            ->map(fn ($ts) => Carbon::parse($ts)->format('Y-m-d\TH:i:s\Z'))
            ->sort()
            ->values()
            ->all();

        $expectedTimestamps = array_column($this->dataset, 'recorded_at');
        sort($expectedTimestamps);

        $this->assertCount(250, $storedTimestamps);
        $this->assertSame($expectedTimestamps, $storedTimestamps);

        $distinctCount = SensorReading::query()
            ->where('sensor_id', $sensor->id)
            ->distinct()
            ->count('recorded_at');
        $this->assertSame(250, $distinctCount);
    }

    public function test_idempotent_rerun_stores_nothing_new(): void
    {
        $this->fakeGondolaHealthy();
        Sensor::create([
            'mac' => self::MAC,
            'device_name' => 'Test Sensor',
            'name' => 'Test',
            'color' => 'var(--series-1)',
        ]);

        $this->artisan('sensors:ingest')->assertExitCode(0);
        $this->assertSame(250, SensorReading::count());

        // Re-fake so a fresh request sequence starts
        $this->fakeGondolaHealthy();

        $this->artisan('sensors:ingest')
            ->assertExitCode(0)
            ->expectsOutputToContain('Synced 0 new readings across 1 sensors (0 duplicates skipped).');

        $this->assertSame(250, SensorReading::count());
    }

    private function fakeGondolaHealthy(): void
    {
        Http::fake(fn (Request $request) => $this->gondolaResponse($request));
    }

    private function gondolaResponse(Request $request): PromiseInterface
    {
        if (! str_contains($request->url(), '/api/v1/readings')) {
            return Http::response('', 404);
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
        $from = $params['from'] ?? '1970-01-01T00:00:00Z';

        $remaining = array_values(array_filter(
            $this->dataset,
            fn (array $row) => $row['recorded_at'] > $from
        ));

        $page = array_slice($remaining, 0, 100);
        $hasMore = count($remaining) > 100;

        return Http::response([
            'mac' => self::MAC,
            'count' => count($page),
            'has_more' => $hasMore,
            'readings' => $page,
        ]);
    }
}
