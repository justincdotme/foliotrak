<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SensorType;
use App\Models\Plant;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    /** @var User */
    private User $user;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @return void */
    public function test_returns_closest_reading_for_single_sensor(): void
    {
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:01', 'name' => 'Desk sensor', 'color' => 'var(--series-1)']);
        $plant->sensors()->attach($sensor);

        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['temperature' => 22.5, 'humidity' => 65.0], 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['temperature' => 23.0, 'humidity' => 60.0], 'recorded_at' => '2026-07-05 14:30:00']);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:22:00Z')
            ->assertOk()
            ->assertJsonPath('data.ambient_temp_c', 23.0)
            ->assertJsonPath('data.ambient_humidity_pct', 60.0)
            ->assertJsonPath('data.sensor_count', 1)
            ->assertJsonPath('data.matched_at', '2026-07-05T14:30:00Z');
    }

    /** @return void */
    public function test_averages_across_multiple_sensors(): void
    {
        $plant = Plant::factory()->create();
        $s1    = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:01', 'name' => 'Sensor 1', 'color' => 'var(--series-1)']);
        $s2    = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:02', 'name' => 'Sensor 2', 'color' => 'var(--series-2)']);
        $plant->sensors()->attach([$s1->id, $s2->id]);

        SensorReading::create(['sensor_id' => $s1->id, 'data' => ['temperature' => 20.0, 'humidity' => 60.0], 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $s2->id, 'data' => ['temperature' => 24.0, 'humidity' => 70.0], 'recorded_at' => '2026-07-05 14:05:00']);

        $response = $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:02:00Z')
            ->assertOk();

        $response->assertJsonPath('data.ambient_temp_c', 22.0);
        $response->assertJsonPath('data.ambient_humidity_pct', 65.0);
        $response->assertJsonPath('data.sensor_count', 2);
        $response->assertJsonMissingPath('data.matched_at');
    }

    /** @return void */
    public function test_returns_204_when_plant_has_no_sensors(): void
    {
        $plant = Plant::factory()->create();

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot')
            ->assertNoContent();
    }

    /** @return void */
    public function test_returns_204_when_no_readings_exist(): void
    {
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:01', 'name' => 'Empty', 'color' => 'var(--series-1)']);
        $plant->sensors()->attach($sensor);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot')
            ->assertNoContent();
    }

    /** @return void */
    public function test_excludes_readings_older_than_one_hour(): void
    {
        $plant = Plant::factory()->create();
        $s1    = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:01', 'name' => 'Fresh', 'color' => 'var(--series-1)']);
        $s2    = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:02', 'name' => 'Stale', 'color' => 'var(--series-2)']);
        $plant->sensors()->attach([$s1->id, $s2->id]);

        SensorReading::create(['sensor_id' => $s1->id, 'data' => ['temperature' => 22.0, 'humidity' => 55.0], 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $s2->id, 'data' => ['temperature' => 30.0, 'humidity' => 80.0], 'recorded_at' => '2026-07-05 12:00:00']);

        $response = $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:10:00Z')
            ->assertOk();

        $response->assertJsonPath('data.sensor_count', 1);
        $response->assertJsonPath('data.ambient_temp_c', 22.0);
        $response->assertJsonPath('data.ambient_humidity_pct', 55.0);
        $response->assertJsonPath('data.matched_at', '2026-07-05T14:00:00Z');
    }

    /** @return void */
    public function test_defaults_to_now_without_at_param(): void
    {
        $this->travelTo('2026-07-05 14:00:00');

        $plant  = Plant::factory()->create();
        $sensor = Sensor::create(['mac' => 'AA:BB:CC:DD:EE:01', 'name' => 'Sensor', 'color' => 'var(--series-1)']);
        $plant->sensors()->attach($sensor);

        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['temperature' => 21.0, 'humidity' => 50.0], 'recorded_at' => '2026-07-05 13:55:00']);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot')
            ->assertOk()
            ->assertJsonPath('data.ambient_temp_c', 21.0)
            ->assertJsonPath('data.sensor_count', 1);
    }

    /** @return void */
    public function test_returns_ambient_lux_for_light_sensor(): void
    {
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create([
            'mac'   => 'AA:BB:CC:DD:EE:10',
            'name'  => 'Window lux',
            'color' => 'var(--series-1)',
            'type'  => SensorType::LightSensor,
        ]);
        $plant->sensors()->attach($sensor);

        SensorReading::create([
            'sensor_id'   => $sensor->id,
            'data'        => ['lux' => 34.38, 'white' => 827, 'als' => 597],
            'recorded_at' => '2026-07-05 14:00:00',
        ]);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:02:00Z')
            ->assertOk()
            ->assertJsonPath('data.ambient_lux', 34.38)
            ->assertJsonPath('data.sensor_count', 1)
            ->assertJsonMissingPath('data.ambient_temp_c')
            ->assertJsonMissingPath('data.ambient_humidity_pct');
    }

    /** @return void */
    public function test_returns_both_hygrometer_and_lux_data_for_mixed_sensors(): void
    {
        $plant = Plant::factory()->create();
        $hygro = Sensor::create([
            'mac'   => 'AA:BB:CC:DD:EE:20',
            'name'  => 'Desk hygro',
            'color' => 'var(--series-1)',
            'type'  => SensorType::Hygrometer,
        ]);
        $lux = Sensor::create([
            'mac'   => 'AA:BB:CC:DD:EE:21',
            'name'  => 'Window lux',
            'color' => 'var(--series-2)',
            'type'  => SensorType::LightSensor,
        ]);
        $plant->sensors()->attach([$hygro->id, $lux->id]);

        SensorReading::create([
            'sensor_id'   => $hygro->id,
            'data'        => ['temperature' => 22.0, 'humidity' => 60.0],
            'recorded_at' => '2026-07-05 14:00:00',
        ]);
        SensorReading::create([
            'sensor_id'   => $lux->id,
            'data'        => ['lux' => 500.0, 'white' => 1200, 'als' => 900],
            'recorded_at' => '2026-07-05 14:01:00',
        ]);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:00:30Z')
            ->assertOk()
            ->assertJsonPath('data.ambient_temp_c', 22.0)
            ->assertJsonPath('data.ambient_humidity_pct', 60.0)
            ->assertJsonPath('data.ambient_lux', 500.0)
            ->assertJsonPath('data.sensor_count', 2)
            ->assertJsonMissingPath('data.matched_at');
    }

    /** @return void */
    public function test_returns_calibrated_soil_moisture_for_moisture_sensor(): void
    {
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create([
            'mac'   => '02:00:5E:AA:00:05',
            'name'  => 'Monstera probe',
            'color' => 'var(--series-3)',
            'type'  => SensorType::Moisture,
        ]);
        $plant->sensors()->attach($sensor);

        $sensor->calibrationPoints()->createMany([
            ['position' => 1, 'raw_value' => 3100],
            ['position' => 5, 'raw_value' => 2200],
            ['position' => 10, 'raw_value' => 1300],
        ]);

        SensorReading::create([
            'sensor_id'   => $sensor->id,
            'data'        => ['moisture' => 2650, 'rssi' => -75],
            'recorded_at' => '2026-07-05 14:00:00',
        ]);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:02:00Z')
            ->assertOk()
            ->assertJsonPath('data.soil_moisture_precise', 3)
            ->assertJsonPath('data.sensor_count', 1)
            ->assertJsonMissingPath('data.ambient_lux');
    }

    /** @return void */
    public function test_soil_moisture_falls_back_to_the_hardware_range(): void
    {
        $plant  = Plant::factory()->create();
        $sensor = Sensor::create([
            'mac'   => '02:00:5E:AA:00:05',
            'name'  => 'Monstera probe',
            'color' => 'var(--series-3)',
            'type'  => SensorType::Moisture,
        ]);
        $plant->sensors()->attach($sensor);

        // With no saved anchors the hardware envelope applies (1 => 4095,
        // 5 => 2048, 10 => 0), so 2975 interpolates to 3.
        SensorReading::create([
            'sensor_id'   => $sensor->id,
            'data'        => ['moisture' => 2975],
            'recorded_at' => '2026-07-05 14:00:00',
        ]);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:02:00Z')
            ->assertOk()
            ->assertJsonPath('data.soil_moisture_precise', 3)
            ->assertJsonPath('data.sensor_count', 1);
    }

    /** @return void */
    public function test_returns_hygrometer_and_moisture_data_for_mixed_sensors(): void
    {
        $plant = Plant::factory()->create();
        $hygro = Sensor::create([
            'mac'   => 'AA:BB:CC:DD:EE:20',
            'name'  => 'Desk hygro',
            'color' => 'var(--series-1)',
            'type'  => SensorType::Hygrometer,
        ]);
        $probe = Sensor::create([
            'mac'   => '02:00:5E:AA:00:05',
            'name'  => 'Monstera probe',
            'color' => 'var(--series-3)',
            'type'  => SensorType::Moisture,
        ]);
        $plant->sensors()->attach([$hygro->id, $probe->id]);

        $probe->calibrationPoints()->createMany([
            ['position' => 1, 'raw_value' => 3100],
            ['position' => 10, 'raw_value' => 1300],
        ]);

        SensorReading::create(['sensor_id' => $hygro->id, 'data' => ['temperature' => 22.0, 'humidity' => 60.0], 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $probe->id, 'data' => ['moisture' => 2200], 'recorded_at' => '2026-07-05 14:01:00']);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot?at=2026-07-05T14:00:30Z')
            ->assertOk()
            ->assertJsonPath('data.ambient_temp_c', 22.0)
            ->assertJsonPath('data.soil_moisture_precise', 6)
            ->assertJsonPath('data.sensor_count', 2);
    }
}
