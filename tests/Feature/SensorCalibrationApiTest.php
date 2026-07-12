<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SensorType;
use App\Models\Sensor;
use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SensorCalibrationApiTest extends TestCase
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

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'position out of range' => [['points' => [['position' => 0, 'value' => 3000], ['position' => 10, 'value' => 1300]]]];
        yield 'duplicate positions' => [['points' => [['position' => 5, 'value' => 3000], ['position' => 5, 'value' => 1300]]]];
        yield 'value above adc range' => [['points' => [['position' => 1, 'value' => 4096], ['position' => 10, 'value' => 1300]]]];
        yield 'value below zero' => [['points' => [['position' => 1, 'value' => -1], ['position' => 10, 'value' => 1300]]]];
    }

    /** @return void */
    public function test_calibration_is_rejected_for_non_moisture_sensors(): void
    {
        $sensor = Sensor::create(['mac' => 'A4:C1:38:AA:00:01', 'name' => 'Hygro', 'color' => 'var(--series-1)']);

        $this->getJson("/api/sensors/{$sensor->id}/calibration")->assertStatus(422);
        $this->putJson("/api/sensors/{$sensor->id}/calibration", [
            'points' => [['position' => 1, 'value' => 3000], ['position' => 10, 'value' => 1300]],
        ])->assertStatus(422);
    }

    /** @return void */
    public function test_show_suggests_the_full_hardware_range(): void
    {
        $sensor = $this->moistureSensor();
        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['moisture' => 3100], 'recorded_at' => '2026-07-11 10:00:00']);
        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['moisture' => 1300], 'recorded_at' => '2026-07-11 11:00:00']);
        SensorReading::create(['sensor_id' => $sensor->id, 'data' => ['moisture' => 2975], 'recorded_at' => '2026-07-11 12:00:00']);

        $this->getJson("/api/sensors/{$sensor->id}/calibration")
            ->assertOk()
            ->assertJsonPath('data.points', [])
            ->assertJsonPath('data.suggested', [
                ['position' => 1, 'value' => 4095],
                ['position' => 5, 'value' => 2048],
                ['position' => 10, 'value' => 0],
            ])
            ->assertJsonPath('data.latest.value', 2975)
            ->assertJsonPath('data.latest.recorded_at', '2026-07-11T12:00:00Z');
    }

    /** @return void */
    public function test_show_suggests_the_hardware_range_before_any_readings_exist(): void
    {
        $sensor = $this->moistureSensor();

        $this->getJson("/api/sensors/{$sensor->id}/calibration")
            ->assertOk()
            ->assertJsonPath('data.suggested', [
                ['position' => 1, 'value' => 4095],
                ['position' => 5, 'value' => 2048],
                ['position' => 10, 'value' => 0],
            ])
            ->assertJsonPath('data.latest', null);
    }

    /** @return void */
    public function test_update_replaces_the_anchor_set(): void
    {
        $sensor = $this->moistureSensor();
        $sensor->calibrationPoints()->create(['position' => 5, 'raw_value' => 2600]);

        $this->putJson("/api/sensors/{$sensor->id}/calibration", [
            'points' => [
                ['position' => 1, 'value' => 3050],
                ['position' => 10, 'value' => 1350],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.points', [
                ['position' => 1, 'value' => 3050],
                ['position' => 10, 'value' => 1350],
            ]);

        $this->assertSame(2, $sensor->calibrationPoints()->count());
    }

    /** @return void */
    public function test_update_with_no_points_clears_the_calibration(): void
    {
        $sensor = $this->moistureSensor();
        $sensor->calibrationPoints()->create(['position' => 5, 'raw_value' => 2600]);

        $this->putJson("/api/sensors/{$sensor->id}/calibration", ['points' => []])
            ->assertOk()
            ->assertJsonPath('data.points', []);

        $this->assertSame(0, $sensor->calibrationPoints()->count());
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return void
     */
    #[DataProvider('invalidPayloads')]
    public function test_update_rejects_invalid_payloads(array $payload): void
    {
        $sensor = $this->moistureSensor();

        $this->putJson("/api/sensors/{$sensor->id}/calibration", $payload)->assertStatus(422);
    }

    /**
     * @return Sensor
     */
    private function moistureSensor(): Sensor
    {
        return Sensor::create([
            'mac'   => 'AC:A7:04:AA:00:05',
            'name'  => 'Monstera probe',
            'color' => 'var(--series-3)',
            'type'  => SensorType::Moisture,
        ]);
    }
}
