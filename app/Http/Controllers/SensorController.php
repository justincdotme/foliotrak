<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SensorReadingSource;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Http\Resources\SensorResource;
use App\Models\Plant;
use App\Models\Sensor;
use App\Models\SensorReading;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SensorController extends Controller
{
    use AuthorizesRequests;

    private const PALETTE_SIZE = 8;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sensor::class);

        return SensorResource::collection(
            Sensor::query()->withCount('plants')->orderBy('name')->get()
        );
    }

    public function store(StoreSensorRequest $request): JsonResponse
    {
        $this->authorize('create', Sensor::class);

        $data = $request->validated();

        $index = Sensor::query()->count() % self::PALETTE_SIZE;
        $data['color'] = 'var(--series-'.($index + 1).')';

        $sensor = Sensor::create($data);
        $sensor->loadCount('plants');

        return SensorResource::make($sensor)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateSensorRequest $request, Sensor $sensor): SensorResource
    {
        $this->authorize('update', $sensor);

        $sensor->update($request->validated());
        $sensor->loadCount('plants');

        return SensorResource::make($sensor);
    }

    public function destroy(Sensor $sensor): Response
    {
        $this->authorize('delete', $sensor);

        $sensor->delete();

        return response()->noContent();
    }

    public function discover(SensorReadingSource $source): JsonResponse
    {
        $this->authorize('viewAny', Sensor::class);

        $baseUrl = config('sensors.base_url');
        $apiKey = config('sensors.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return response()->json([
                'data' => [],
                'error' => 'Gateway URL or API key not set',
            ]);
        }

        try {
            $devices = $source->discoverSensors();
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }

        $registeredMacs = Sensor::query()->pluck('mac')->all();

        $discovered = array_map(function ($device) use ($registeredMacs) {
            $reading = $device->lastReading;

            return [
                'mac' => $device->mac,
                'device_name' => $device->deviceName,
                'last_reading' => $reading ? [
                    'temperature' => $reading->temperature,
                    'humidity' => $reading->humidity,
                    'battery' => $reading->battery,
                    'rssi' => $reading->rssi,
                    'recorded_at' => $reading->recordedAt->format('Y-m-d\TH:i:s\Z'),
                ] : null,
                'registered' => in_array($device->mac, $registeredMacs, true),
            ];
        }, $devices);

        return response()->json(['data' => $discovered]);
    }

    public function testConnection(SensorReadingSource $source): JsonResponse
    {
        $this->authorize('viewAny', Sensor::class);

        $baseUrl = config('sensors.base_url');
        $apiKey = config('sensors.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return response()->json([
                'data' => [
                    'status' => 'not_configured',
                    'error' => 'Gateway URL or API key not set',
                ],
            ]);
        }

        $status = $source->testConnection();

        return response()->json([
            'data' => array_filter([
                'status' => $status->status,
                'collector_running' => $status->collectorRunning,
                'sensors_seen' => $status->sensorsSeen,
                'uptime_seconds' => $status->uptimeSeconds,
                'error' => $status->error,
            ], fn ($v) => $v !== null),
        ]);
    }

    public function plantReadings(Request $request, Plant $plant): JsonResponse
    {
        $this->authorize('view', $plant);

        $range = $request->query('range', 'week');
        $since = match ($range) {
            'day' => Carbon::now()->subDay(),
            'month' => Carbon::now()->subDays(30),
            default => Carbon::now()->subDays(7),
        };

        $sensorIds = $plant->sensors()->pluck('sensors.id');

        $readings = SensorReading::query()
            ->whereIn('sensor_id', $sensorIds)
            ->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get();

        $grouped = $readings->groupBy('sensor_id');

        $sensors = [];
        foreach ($plant->sensors as $sensor) {
            /** @var Collection<int, SensorReading> $sensorReadings */
            $sensorReadings = $grouped->get($sensor->id, collect());

            $formattedReadings = [];
            foreach ($sensorReadings as $r) {
                /** @var Carbon $recordedAt */
                $recordedAt = $r->recorded_at;
                $formattedReadings[] = [
                    'temperature_f' => round($r->temperature * 9 / 5 + 32, 1),
                    'humidity' => $r->humidity,
                    'recorded_at' => $recordedAt->format('Y-m-d\TH:i:s\Z'),
                ];
            }

            $sensors[] = [
                'id' => $sensor->id,
                'name' => $sensor->name,
                'color' => $sensor->color,
                'readings' => $formattedReadings,
            ];
        }

        return response()->json([
            'data' => [
                'sensors' => $sensors,
                'granularity_minutes' => config('sensors.granularity'),
            ],
        ]);
    }
}
