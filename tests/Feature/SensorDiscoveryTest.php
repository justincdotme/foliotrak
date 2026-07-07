<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SensorDiscoveryTest extends TestCase
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

        config([
            'sensors.base_url' => 'https://gateway.test',
            'sensors.api_key'  => 'test-key-not-real',
        ]);
    }

    /** @return void */
    public function test_discover_surfaces_hardware_identity_and_suggested_type(): void
    {
        Http::fake([
            'https://gateway.test/*' => Http::response([
                'sensors' => [
                    [
                        'mac'          => 'A4:C1:38:7D:3A:14',
                        'device_name'  => 'GVH5075_3A14',
                        'sensor_type'  => 'govee_h5075',
                        'last_reading' => [
                            'measurements' => ['temperature' => 22.5, 'humidity' => 45.5],
                            'battery'      => 87,
                            'rssi'         => -42,
                            'recorded_at'  => '2026-07-06T14:00:00Z',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/sensors/discover')
            ->assertOk()
            ->assertJsonPath('data.0.mac', 'A4:C1:38:7D:3A:14')
            ->assertJsonPath('data.0.sensor_type', 'govee_h5075')
            ->assertJsonPath('data.0.suggested_type', 'hygrometer')
            ->assertJsonPath('data.0.last_reading.temperature', 22.5)
            ->assertJsonPath('data.0.last_reading.humidity', 45.5)
            ->assertJsonPath('data.0.last_reading.battery', 87)
            ->assertJsonPath('data.0.registered', false)
            ->assertJsonMissingPath('data.0.last_reading.measurements');
    }

    /** @return void */
    public function test_discover_handles_the_legacy_flat_gateway_shape(): void
    {
        Http::fake([
            'https://gateway.test/*' => Http::response([
                'sensors' => [
                    [
                        'mac'          => 'A4:C1:38:7D:3A:14',
                        'device_name'  => 'GVH5075_3A14',
                        'last_reading' => [
                            'temperature' => 22.5,
                            'humidity'    => 45.5,
                            'battery'     => 87,
                            'rssi'        => -42,
                            'recorded_at' => '2026-07-06T14:00:00Z',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/sensors/discover')
            ->assertOk()
            ->assertJsonPath('data.0.sensor_type', null)
            ->assertJsonPath('data.0.suggested_type', null)
            ->assertJsonPath('data.0.last_reading.temperature', 22.5)
            ->assertJsonPath('data.0.last_reading.humidity', 45.5);
    }

    /** @return void */
    public function test_registration_persists_hardware_type(): void
    {
        $this->postJson('/api/sensors', [
            'mac'           => '11:22:33:44:55:66',
            'device_name'   => 'GVH5075_5566',
            'hardware_type' => 'govee_h5075',
            'name'          => 'Office sensor',
            'type'          => 'hygrometer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.hardware_type', 'govee_h5075');

        $this->assertSame('govee_h5075', Sensor::query()->sole()->hardware_type);
    }
}
