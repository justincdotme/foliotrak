<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
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

    /**
     * @return array<string, array{string}>
     */
    public static function invalidMacProvider(): array
    {
        return [
            'no separators'      => ['AABBCCDDEEFF'],
            'dash separators'    => ['AA-BB-CC-DD-EE-FF'],
            'too few octets'     => ['AA:BB:CC:DD:EE'],
            'non-hex characters' => ['GG:HH:II:JJ:KK:LL'],
        ];
    }

    /** @return void */
    public function test_discover_surfaces_hardware_identity_and_suggested_type(): void
    {
        Http::fake([
            'https://gateway.test/*' => Http::response([
                'sensors' => [
                    [
                        'mac'          => '02:00:5E:AA:00:01',
                        'device_name'  => 'SENSOR_0001',
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
            ->assertJsonPath('data.0.mac', '02:00:5E:AA:00:01')
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
                        'mac'          => '02:00:5E:AA:00:01',
                        'device_name'  => 'SENSOR_0001',
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
            'device_name'   => 'SENSOR_5566',
            'hardware_type' => 'govee_h5075',
            'name'          => 'Office sensor',
            'type'          => 'hygrometer',
        ])
            ->assertCreated()
            ->assertJsonPath('data.hardware_type', 'govee_h5075');

        $this->assertSame('govee_h5075', Sensor::query()->sole()->hardware_type);
    }

    /** @return void */
    public function test_discover_suggests_the_moisture_type_for_gondola_moisture_hardware(): void
    {
        Http::fake([
            'https://gateway.test/*' => Http::response([
                'sensors' => [
                    [
                        'mac'          => '02:00:5E:AA:00:05',
                        'device_name'  => 'Gondola-Moisture-01',
                        'sensor_type'  => 'gondola_moisture',
                        'last_reading' => [
                            'measurements' => ['moisture' => 2975],
                            'battery'      => null,
                            'rssi'         => -75,
                            'recorded_at'  => '2026-07-12T00:22:24Z',
                        ],
                    ],
                ],
            ]),
        ]);

        $this->getJson('/api/sensors/discover')
            ->assertOk()
            ->assertJsonPath('data.0.sensor_type', 'gondola_moisture')
            ->assertJsonPath('data.0.suggested_type', 'moisture')
            ->assertJsonPath('data.0.last_reading.moisture', 2975);
    }

    /** @return void */
    public function test_registration_accepts_the_moisture_type(): void
    {
        $this->postJson('/api/sensors', [
            'mac'           => '02:00:5E:AA:00:05',
            'device_name'   => 'Gondola-Moisture-01',
            'hardware_type' => 'gondola_moisture',
            'name'          => 'Monstera probe',
            'type'          => 'moisture',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', 'moisture');
    }

    /** @return void */
    public function test_registration_rejects_unknown_types(): void
    {
        $this->postJson('/api/sensors', [
            'mac'  => '02:00:5E:AA:00:05',
            'name' => 'Mystery sensor',
            'type' => 'seismometer',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    /** @return void */
    public function test_discover_returns_generic_error_on_unexpected_failure(): void
    {
        Http::fake([
            'https://gateway.test/*' => fn () => throw new RuntimeException(
                'cURL error 7: Failed to connect to 10.1.20.50 port 8080',
            ),
        ]);

        $response = $this->getJson('/api/sensors/discover');

        $response->assertOk()
            ->assertJsonPath('error', 'Unable to reach sensor gateway');

        $this->assertStringNotContainsString(
            '10.1.20.50',
            json_encode($response->json()),
        );
    }

    /** @return void */
    public function test_test_connection_returns_generic_error_when_gateway_unreachable(): void
    {
        Http::fake([
            'https://gateway.test/api/v1/health' => fn () => throw new ConnectionException(
                'cURL error 7: Failed to connect to 10.1.20.50 port 8080',
            ),
        ]);

        $response = $this->postJson('/api/sensors/test-connection');

        $response->assertOk()
            ->assertJsonPath('data.status', 'unreachable')
            ->assertJsonPath('data.error', 'Unable to reach sensor gateway');

        $this->assertStringNotContainsString(
            '10.1.20.50',
            json_encode($response->json()),
        );
    }

    /**
     * @param string $mac
     *
     * @return void
     */
    #[DataProvider('invalidMacProvider')]
    public function test_registration_rejects_invalid_mac_format(string $mac): void
    {
        $this->postJson('/api/sensors', [
            'mac'  => $mac,
            'name' => 'Test sensor',
            'type' => 'hygrometer',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('mac');
    }
}
