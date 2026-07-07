<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\SensorReadingSource;
use App\Enums\SensorType;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Http\Resources\SensorResource;
use App\Models\Plant;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Services\Sensors\Transformers\HygrometerTransformer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Throwable;

class SensorController extends Controller
{
    use AuthorizesRequests;

    /**
     * Number of color palette entries
     *
     * @var int
     */
    private const PALETTE_SIZE = 8;

    /**
     * @return AnonymousResourceCollection
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sensor::class);

        return SensorResource::collection(
            Sensor::query()->withCount('plants')->orderBy('name')->get(),
        );
    }

    /**
     * @return JsonResponse
     */
    public function sensorTypes(): JsonResponse
    {
        $types = array_map(
            fn (SensorType $type) => ['value' => $type->value, 'label' => $type->label()],
            SensorType::cases(),
        );

        return response()->json(['data' => $types]);
    }

    /**
     * @param StoreSensorRequest $request
     *
     * @return JsonResponse
     */
    public function store(StoreSensorRequest $request): JsonResponse
    {
        $this->authorize('create', Sensor::class);

        $data = $request->validated();

        $index         = Sensor::query()->count() % self::PALETTE_SIZE;
        $data['color'] = 'var(--series-' . ($index + 1) . ')';

        $sensor = Sensor::create($data);
        $sensor->loadCount('plants');

        return SensorResource::make($sensor)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param UpdateSensorRequest $request
     * @param Sensor              $sensor
     *
     * @return SensorResource
     */
    public function update(UpdateSensorRequest $request, Sensor $sensor): SensorResource
    {
        $this->authorize('update', $sensor);

        $sensor->update($request->validated());
        $sensor->loadCount('plants');

        return SensorResource::make($sensor);
    }

    /**
     * @param Sensor $sensor
     *
     * @return Response
     */
    public function destroy(Sensor $sensor): Response
    {
        $this->authorize('delete', $sensor);

        $sensor->delete();

        return response()->noContent();
    }

    /**
     * @param SensorReadingSource $source
     *
     * @return JsonResponse
     */
    public function discover(SensorReadingSource $source): JsonResponse
    {
        $this->authorize('viewAny', Sensor::class);

        $baseUrl = config('sensors.base_url');
        $apiKey  = config('sensors.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return response()->json([
                'data'  => [],
                'error' => 'Gateway URL or API key not set',
            ]);
        }

        try {
            $devices = $source->discoverSensors();
        } catch (Throwable $e) {
            return response()->json([
                'data'  => [],
                'error' => $e->getMessage(),
            ]);
        }

        $registeredMacs = Sensor::query()->pluck('mac')->all();

        $discovered = array_map(function ($device) use ($registeredMacs) {
            $reading = $device->lastReading;

            return [
                'mac'            => $device->mac,
                'device_name'    => $device->deviceName,
                'sensor_type'    => $device->sensorType,
                'suggested_type' => SensorType::forHardware($device->sensorType)?->value,
                'last_reading'   => $reading ? array_merge(
                    $reading->data,
                    ['recorded_at' => $reading->recordedAt->format('Y-m-d\TH:i:s\Z')],
                ) : null,
                'registered' => in_array($device->mac, $registeredMacs, true),
            ];
        }, $devices);

        return response()->json(['data' => $discovered]);
    }

    /**
     * @param SensorReadingSource $source
     *
     * @return JsonResponse
     */
    public function testConnection(SensorReadingSource $source): JsonResponse
    {
        $this->authorize('viewAny', Sensor::class);

        $baseUrl = config('sensors.base_url');
        $apiKey  = config('sensors.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return response()->json([
                'data' => [
                    'status' => 'not_configured',
                    'error'  => 'Gateway URL or API key not set',
                ],
            ]);
        }

        $status = $source->testConnection();

        return response()->json([
            'data' => array_filter([
                'status'            => $status->status,
                'collector_running' => $status->collectorRunning,
                'sensors_seen'      => $status->sensorsSeen,
                'uptime_seconds'    => $status->uptimeSeconds,
                'error'             => $status->error,
            ], fn ($v) => $v !== null),
        ]);
    }

    /**
     * @param Request $request
     * @param Plant   $plant
     *
     * @return JsonResponse
     */
    public function plantReadings(Request $request, Plant $plant): JsonResponse
    {
        $this->authorize('view', $plant);

        $range = $request->query('range', 'week');
        $since = match ($range) {
            'day'   => Carbon::now()->subDay(),
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
            $transformer    = $sensor->type->transformer();

            $formattedReadings = [];

            foreach ($sensorReadings as $r) {
                /** @var Carbon $recordedAt */
                $recordedAt = $r->recorded_at;
                $vo         = $transformer->hydrate($r->data);

                $formattedReadings[] = array_merge(
                    $vo->toApiValues(),
                    ['recorded_at' => $recordedAt->format('Y-m-d\TH:i:s\Z')],
                );
            }

            $sensors[] = [
                'id'       => $sensor->id,
                'name'     => $sensor->name,
                'color'    => $sensor->color,
                'type'     => $sensor->type->value,
                'fields'   => $transformer->chartFields(),
                'readings' => $formattedReadings,
            ];
        }

        return response()->json([
            'data' => [
                'sensors'             => $sensors,
                'granularity_minutes' => config('sensors.granularity'),
            ],
        ]);
    }

    /**
     * @param Request $request
     * @param Plant   $plant
     *
     * @return JsonResponse|Response
     */
    public function snapshot(Request $request, Plant $plant): JsonResponse|Response
    {
        $this->authorize('view', $plant);

        $request->validate([
            'at' => ['sometimes', 'date'],
        ]);

        $at = $request->has('at')
            ? Carbon::parse($request->query('at'))
            : Carbon::now();

        $sensorIds = $plant->sensors()
            ->where('type', SensorType::Hygrometer)
            ->pluck('sensors.id');

        if ($sensorIds->isEmpty()) {
            return response()->noContent();
        }

        $readings = collect();

        foreach ($sensorIds as $sensorId) {
            $before = SensorReading::query()
                ->where('sensor_id', $sensorId)
                ->where('recorded_at', '<=', $at)
                ->orderByDesc('recorded_at')
                ->first();

            $after = SensorReading::query()
                ->where('sensor_id', $sensorId)
                ->where('recorded_at', '>', $at)
                ->orderBy('recorded_at')
                ->first();

            $candidates = collect([$before, $after])->filter();

            if ($candidates->isEmpty()) {
                continue;
            }

            /** @var SensorReading $closest */
            $closest = $candidates->sortBy(function (SensorReading $r) use ($at): float {
                /** @var Carbon $recordedAt */
                $recordedAt = $r->recorded_at;

                return abs($recordedAt->diffInSeconds($at));
            })->first();

            /** @var Carbon $recordedAt */
            $recordedAt = $closest->recorded_at;

            if (abs($recordedAt->diffInSeconds($at)) > 3600) {
                continue;
            }

            $readings->push($closest);
        }

        if ($readings->isEmpty()) {
            return response()->noContent();
        }

        $transformer = new HygrometerTransformer;

        $response = [
            'ambient_temp_c' => (float) number_format(
                $readings->avg(fn ($r) => $transformer->hydrate($r->data)->temperature),
                1,
                '.',
                '',
            ),
            'ambient_humidity_pct' => (float) number_format(
                $readings->avg(fn ($r) => $transformer->hydrate($r->data)->humidity),
                1,
                '.',
                '',
            ),
            'sensor_count' => $readings->count(),
        ];

        if ($readings->count() === 1) {
            $response['matched_at'] = $readings->first()->recorded_at->format('Y-m-d\TH:i:s\Z');
        }

        return response()->json(['data' => $response], options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
