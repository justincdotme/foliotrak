<?php

declare(strict_types=1);

namespace Tests\Feature;

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

        SensorReading::create(['sensor_id' => $sensor->id, 'temperature' => 22.5, 'humidity' => 65.0, 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $sensor->id, 'temperature' => 23.0, 'humidity' => 60.0, 'recorded_at' => '2026-07-05 14:30:00']);

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

        SensorReading::create(['sensor_id' => $s1->id, 'temperature' => 20.0, 'humidity' => 60.0, 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $s2->id, 'temperature' => 24.0, 'humidity' => 70.0, 'recorded_at' => '2026-07-05 14:05:00']);

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

        SensorReading::create(['sensor_id' => $s1->id, 'temperature' => 22.0, 'humidity' => 55.0, 'recorded_at' => '2026-07-05 14:00:00']);
        SensorReading::create(['sensor_id' => $s2->id, 'temperature' => 30.0, 'humidity' => 80.0, 'recorded_at' => '2026-07-05 12:00:00']);

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

        SensorReading::create(['sensor_id' => $sensor->id, 'temperature' => 21.0, 'humidity' => 50.0, 'recorded_at' => '2026-07-05 13:55:00']);

        $this->getJson('/api/plants/' . $plant->id . '/sensor-snapshot')
            ->assertOk()
            ->assertJsonPath('data.ambient_temp_c', 21.0)
            ->assertJsonPath('data.sensor_count', 1);
    }
}
