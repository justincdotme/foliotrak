<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SensorType;
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

    /** @var Carbon */
    private Carbon $startTime;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sensors.base_url' => self::GATEWAY_URL,
            'sensors.api_key'  => self::API_KEY,
        ]);

        $this->startTime = Carbon::now('UTC')->subHours(5);

        for ($i = 0; $i < 250; $i++) {
            $this->dataset[] = [
                'temperature' => round(20.0 + $i * 0.1, 1),
                'humidity'    => round(50.0 + $i * 0.05, 2),
                'battery'     => 95,
                'rssi'        => -60,
                'recorded_at' => $this->startTime->copy()->addSeconds($i * 60)->format('Y-m-d\TH:i:s\Z'),
            ];
        }
    }

    /** @return void */
    public function test_multi_page_backfill_produces_gap_free_series(): void
    {
        $this->fakeGondolaHealthy();
        Sensor::create([
            'mac'         => self::MAC,
            'device_name' => 'Test Sensor',
            'name'        => 'Test',
            'color'       => 'var(--series-1)',
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

        // Whole-number floats (e.g. 20.0) lose their decimal through the JSON
        // round-trip and decode back as int, so these compare by value.
        $reading = SensorReading::first();
        $this->assertEquals($this->dataset[0]['temperature'], $reading->data['temperature']);
        $this->assertEquals($this->dataset[0]['humidity'], $reading->data['humidity']);
        $this->assertSame($this->dataset[0]['battery'], $reading->data['battery']);
        $this->assertSame($this->dataset[0]['rssi'], $reading->data['rssi']);
    }

    /** @return void */
    public function test_interrupted_backfill_resumes_without_gaps_or_duplicates(): void
    {
        $sensor = Sensor::create([
            'mac'         => self::MAC,
            'device_name' => 'Test Sensor',
            'name'        => 'Test',
            'color'       => 'var(--series-1)',
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

    /** @return void */
    public function test_idempotent_rerun_stores_nothing_new(): void
    {
        $this->fakeGondolaHealthy();
        Sensor::create([
            'mac'         => self::MAC,
            'device_name' => 'Test Sensor',
            'name'        => 'Test',
            'color'       => 'var(--series-1)',
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

    /** @return void */
    public function test_ingests_legacy_flat_readings_shape(): void
    {
        Sensor::create([
            'mac'         => self::MAC,
            'device_name' => 'Test Sensor',
            'name'        => 'Test',
            'color'       => 'var(--series-1)',
        ]);

        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), '/api/v1/readings')) {
                return Http::response('', 404);
            }

            return Http::response([
                'mac'      => self::MAC,
                'count'    => 2,
                'has_more' => false,
                'readings' => [
                    [
                        'temperature' => 21.5,
                        'humidity'    => 48.5,
                        'battery'     => 90,
                        'rssi'        => -55,
                        'recorded_at' => $this->startTime->format('Y-m-d\TH:i:s\Z'),
                    ],
                    [
                        'temperature' => 21.7,
                        'humidity'    => 48.7,
                        'battery'     => 90,
                        'rssi'        => -55,
                        'recorded_at' => $this->startTime->copy()->addMinute()->format('Y-m-d\TH:i:s\Z'),
                    ],
                ],
            ]);
        });

        $this->artisan('sensors:ingest')->assertExitCode(0);

        $this->assertSame(2, SensorReading::count());

        $reading = SensorReading::query()->orderBy('recorded_at')->first();
        $this->assertEquals(21.5, $reading->data['temperature']);
        $this->assertEquals(48.5, $reading->data['humidity']);
        $this->assertSame(90, $reading->data['battery']);
    }

    /** @return void */
    public function test_unreadable_readings_are_skipped_and_other_sensors_still_ingest(): void
    {
        $misregistered = Sensor::create([
            'mac'         => '11:22:33:44:55:66',
            'device_name' => 'ESP32 light',
            'name'        => 'Light',
            'color'       => 'var(--series-2)',
        ]);
        $healthy = Sensor::create([
            'mac'         => self::MAC,
            'device_name' => 'Test Sensor',
            'name'        => 'Test',
            'color'       => 'var(--series-1)',
        ]);

        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), '/api/v1/readings')) {
                return Http::response('', 404);
            }

            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);

            if (($params['mac'] ?? '') === '11:22:33:44:55:66') {
                // A photosensor payload for a sensor registered as a hygrometer.
                return Http::response([
                    'mac'      => '11:22:33:44:55:66',
                    'count'    => 1,
                    'has_more' => false,
                    'readings' => [
                        [
                            'sensor_type'  => 'esp32_veml7700',
                            'measurements' => ['lux' => 12000],
                            'battery'      => 88,
                            'rssi'         => -50,
                            'recorded_at'  => $this->startTime->format('Y-m-d\TH:i:s\Z'),
                        ],
                    ],
                ]);
            }

            return $this->gondolaResponse($request);
        });

        $this->artisan('sensors:ingest')
            ->expectsOutputToContain('Skipped 1 unreadable readings for 11:22:33:44:55:66.')
            ->assertExitCode(0);

        $this->assertSame(0, SensorReading::where('sensor_id', $misregistered->id)->count());
        $this->assertSame(250, SensorReading::where('sensor_id', $healthy->id)->count());
    }

    /** @return void */
    public function test_moisture_readings_flow_through_the_moisture_transformer(): void
    {
        Sensor::create([
            'mac'         => 'AC:A7:04:BC:A5:62',
            'device_name' => 'Gondola-Moisture-01',
            'name'        => 'Monstera probe',
            'color'       => 'var(--series-3)',
            'type'        => SensorType::Moisture,
        ]);

        $recordedAt = Carbon::now('UTC')->subMinutes(30)->format('Y-m-d\TH:i:s\Z');

        Http::fake([
            self::GATEWAY_URL . '/api/v1/readings*' => Http::response([
                'mac'      => 'AC:A7:04:BC:A5:62',
                'count'    => 1,
                'has_more' => false,
                'readings' => [
                    [
                        'sensor_type'  => 'gondola_moisture',
                        'measurements' => ['moisture' => 2975],
                        'battery'      => null,
                        'rssi'         => -75,
                        'recorded_at'  => $recordedAt,
                    ],
                ],
            ]),
        ]);

        $this->artisan('sensors:ingest')->assertExitCode(0);

        $reading = SensorReading::query()->sole();
        $this->assertSame(['moisture' => 2975, 'rssi' => -75], $reading->data);
    }

    /** @return void */
    private function fakeGondolaHealthy(): void
    {
        Http::fake(fn (Request $request) => $this->gondolaResponse($request));
    }

    /**
     * @param Request $request
     *
     * @return PromiseInterface
     */
    private function gondolaResponse(Request $request): PromiseInterface
    {
        if (! str_contains($request->url(), '/api/v1/readings')) {
            return Http::response('', 404);
        }

        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $params);
        $from = $params['from'] ?? '1970-01-01T00:00:00Z';

        $remaining = array_values(array_filter(
            $this->dataset,
            fn (array $row) => $row['recorded_at'] > $from,
        ));

        $page    = array_slice($remaining, 0, 100);
        $hasMore = count($remaining) > 100;

        // The fake speaks the gateway's current nested shape; the legacy flat
        // shape is covered by its own test.
        return Http::response([
            'mac'      => self::MAC,
            'count'    => count($page),
            'has_more' => $hasMore,
            'readings' => array_map(static fn (array $row) => [
                'sensor_type'  => 'govee_h5075',
                'measurements' => [
                    'temperature' => $row['temperature'],
                    'humidity'    => $row['humidity'],
                ],
                'battery'     => $row['battery'],
                'rssi'        => $row['rssi'],
                'recorded_at' => $row['recorded_at'],
            ], $page),
        ]);
    }
}
