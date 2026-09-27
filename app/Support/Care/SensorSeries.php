<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Enums\SensorType;
use App\Models\Sensor;
use App\Services\Sensors\MoistureCalibration;
use App\Services\Sensors\Transformers\MoistureTransformer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The sensor data the care algorithms need, read once per request. Ambient
 * conditions are aggregated to daily means in SQL and moisture rows are
 * fetched only for probes, so a page load never carries the reading table
 * into PHP.
 */
final class SensorSeries
{
    /** Days of ambient history the interval estimator conditions on. */
    public const AMBIENT_DAYS = 90;

    /** Days of moisture history the soil evidence draws from. */
    public const MOISTURE_DAYS = 30;

    /** @var array<int, array<string, array{humidity: float|null, temp: float|null}>>|null */
    private ?array $ambient = null;

    /** @var array<int, list<SoilReading>>|null */
    private ?array $moisture = null;

    /**
     * Daily mean humidity and temperature for one hygrometer, keyed by Y-m-d.
     *
     * @param integer $sensorId
     *
     * @return array<string, array{humidity: float|null, temp: float|null}>
     */
    public function ambientByDay(int $sensorId): array
    {
        $this->ambient ??= $this->loadAmbient();

        return $this->ambient[$sensorId] ?? [];
    }

    /**
     * Calibrated moisture readings for one probe, oldest first.
     *
     * @param integer $sensorId
     *
     * @return list<SoilReading>
     */
    public function moistureReadings(int $sensorId): array
    {
        $this->moisture ??= $this->loadMoisture();

        return $this->moisture[$sensorId] ?? [];
    }

    /**
     * @return array<int, array<string, array{humidity: float|null, temp: float|null}>>
     */
    private function loadAmbient(): array
    {
        $grammar     = DB::connection()->getQueryGrammar();
        $humidity    = $grammar->wrap('sensor_readings.data->humidity');
        $temperature = $grammar->wrap('sensor_readings.data->temperature');
        $day         = 'date(' . $grammar->wrap('sensor_readings.recorded_at') . ')';

        $rows = DB::table('sensor_readings')
            ->join('sensors', 'sensors.id', '=', 'sensor_readings.sensor_id')
            ->where('sensors.type', SensorType::Hygrometer->value)
            ->where('sensor_readings.recorded_at', '>=', Carbon::now()->subDays(self::AMBIENT_DAYS))
            ->groupBy('sensor_readings.sensor_id', DB::raw($day))
            ->selectRaw(
                'sensor_readings.sensor_id as sensor_id, ' . $day . ' as day, '
                . 'avg(' . $humidity . ') as humidity, avg(' . $temperature . ') as temperature',
            )
            ->get();

        $bySensor = [];

        foreach ($rows as $row) {
            $bySensor[(int) $row->sensor_id][(string) $row->day] = [
                'humidity' => $row->humidity === null ? null : (float) $row->humidity,
                'temp'     => $row->temperature === null ? null : (float) $row->temperature,
            ];
        }

        return $bySensor;
    }

    /**
     * @return array<int, list<SoilReading>>
     */
    private function loadMoisture(): array
    {
        $sensors = Sensor::query()
            ->where('type', SensorType::Moisture->value)
            ->with('calibrationPoints')
            ->get();

        if ($sensors->isEmpty()) {
            return [];
        }

        /** @var array<int, list<array{position: int, value: int}>> $points */
        $points = $sensors
            ->mapWithKeys(fn (Sensor $sensor): array => [
                $sensor->id => MoistureCalibration::pointsFrom($sensor->calibrationPoints),
            ])
            ->all();

        $rows = DB::table('sensor_readings')
            ->whereIn('sensor_id', $sensors->modelKeys())
            ->where('recorded_at', '>=', Carbon::now()->subDays(self::MOISTURE_DAYS))
            ->orderBy('recorded_at')
            ->get(['sensor_id', 'recorded_at', 'data']);

        $transformer = new MoistureTransformer;
        $bySensor    = [];

        foreach ($rows as $row) {
            $sensorId = (int) $row->sensor_id;
            $stored   = json_decode((string) $row->data, true);

            if (! is_array($stored) || ! isset($stored['moisture'])) {
                continue;
            }

            $position = MoistureCalibration::scale(
                (float) $transformer->hydrate($stored)->moisture,
                $points[$sensorId],
            );

            if ($position === null) {
                continue;
            }

            $bySensor[$sensorId][] = new SoilReading(
                value: (float) $position,
                at: Carbon::parse((string) $row->recorded_at),
                source: 'sensor',
            );
        }

        return $bySensor;
    }
}
