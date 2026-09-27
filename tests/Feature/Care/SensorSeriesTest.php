<?php

declare(strict_types=1);

namespace Tests\Feature\Care;

use App\Enums\SensorType;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Support\Care\SensorSeries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SensorSeriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-08-22 12:00:00'));
    }

    /**
     * The aggregate has to happen in SQL. One row per reading would put the
     * whole table into PHP on every page load.
     *
     * @return void
     */
    public function test_ambient_is_one_row_per_sensor_day_regardless_of_reading_count(): void
    {
        $sensor = $this->sensor(SensorType::Hygrometer, '02:00:5E:AA:00:01');

        foreach ([0, 15, 30, 45] as $minutes) {
            SensorReading::create([
                'sensor_id'   => $sensor->id,
                'recorded_at' => Carbon::parse('2026-08-20 10:00:00')->addMinutes($minutes),
                'data'        => ['humidity' => 50.0 + $minutes / 15, 'temperature' => 20.0],
            ]);
        }

        DB::enableQueryLog();
        $ambient = (new SensorSeries)->ambientByDay($sensor->id);
        $queries = DB::getQueryLog();

        $this->assertSame(['2026-08-20'], array_keys($ambient));
        $this->assertEqualsWithDelta(51.5, $ambient['2026-08-20']['humidity'], 0.01);
        $this->assertEqualsWithDelta(20.0, $ambient['2026-08-20']['temp'], 0.01);
        $this->assertCount(1, $queries);
    }

    /**
     * @return void
     */
    public function test_readings_outside_the_window_never_reach_php(): void
    {
        $sensor = $this->sensor(SensorType::Hygrometer, '02:00:5E:AA:00:02');

        SensorReading::create([
            'sensor_id'   => $sensor->id,
            'recorded_at' => Carbon::now()->subDays(SensorSeries::AMBIENT_DAYS + 5),
            'data'        => ['humidity' => 90.0, 'temperature' => 30.0],
        ]);

        $this->assertSame([], (new SensorSeries)->ambientByDay($sensor->id));
    }

    /**
     * @return void
     */
    public function test_moisture_readings_are_calibrated_and_chronological(): void
    {
        $sensor = $this->sensor(SensorType::Moisture, '02:00:5E:AA:00:03');

        foreach ([['2026-08-20 09:00:00', 2048], ['2026-08-19 09:00:00', 0]] as [$at, $raw]) {
            SensorReading::create([
                'sensor_id'   => $sensor->id,
                'recorded_at' => Carbon::parse($at),
                'data'        => ['moisture' => $raw],
            ]);
        }

        $readings = (new SensorSeries)->moistureReadings($sensor->id);

        $this->assertCount(2, $readings);
        $this->assertSame('2026-08-19', $readings[0]->at->format('Y-m-d'));
        $this->assertEqualsWithDelta(10.0, $readings[0]->value, 0.01);
        $this->assertEqualsWithDelta(5.0, $readings[1]->value, 0.01);
        $this->assertSame('sensor', $readings[0]->source);
    }

    /**
     * A hygrometer's rows must never be pulled by the moisture query, and a
     * probe's rows must never be averaged into ambient conditions.
     *
     * @return void
     */
    public function test_each_query_reads_only_the_sensor_type_it_serves(): void
    {
        $hygrometer = $this->sensor(SensorType::Hygrometer, '02:00:5E:AA:00:04');
        $probe      = $this->sensor(SensorType::Moisture, '02:00:5E:AA:00:05');

        SensorReading::create([
            'sensor_id'   => $hygrometer->id,
            'recorded_at' => Carbon::parse('2026-08-20 09:00:00'),
            'data'        => ['humidity' => 55.0, 'temperature' => 21.0],
        ]);

        SensorReading::create([
            'sensor_id'   => $probe->id,
            'recorded_at' => Carbon::parse('2026-08-20 09:00:00'),
            'data'        => ['moisture' => 2048],
        ]);

        $series = new SensorSeries;

        $this->assertSame([], $series->ambientByDay($probe->id));
        $this->assertSame([], $series->moistureReadings($hygrometer->id));
        $this->assertCount(1, $series->moistureReadings($probe->id));
    }

    /**
     * Each sensor needs a distinct mac; the column is unique.
     *
     * @param SensorType $type
     * @param string     $mac
     *
     * @return Sensor
     */
    private function sensor(SensorType $type, string $mac): Sensor
    {
        return Sensor::create([
            'mac'   => $mac,
            'name'  => 'Test ' . $type->value,
            'color' => 'var(--series-1)',
            'type'  => $type,
        ]);
    }
}
