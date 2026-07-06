<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sensors;

use App\Services\Sensors\Transformers\HygrometerTransformer;
use App\Services\Sensors\ValueObjects\HygrometerReading;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HygrometerTransformerTest extends TestCase
{
    /** @var HygrometerTransformer */
    private HygrometerTransformer $transformer;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new HygrometerTransformer;
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'full data keeps battery and rssi' => [
            ['temperature' => 22.5, 'humidity' => 65.0, 'battery' => 95, 'rssi' => -60],
            ['temperature' => 22.5, 'humidity' => 65.0, 'battery' => 95, 'rssi' => -60],
        ];
        yield 'missing device meta omits null keys' => [
            ['temperature' => 22.5, 'humidity' => 65.0],
            ['temperature' => 22.5, 'humidity' => 65.0],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function rawReadingCases(): iterable
    {
        yield 'full data' => [['temperature' => 22.5, 'humidity' => 65.0, 'battery' => 95, 'rssi' => -60]];
        yield 'no device meta' => [['temperature' => 22.5, 'humidity' => 65.0]];
    }

    /**
     * @param array<string, mixed> $rawData
     * @param array<string, mixed> $expected
     *
     * @return void
     */
    #[DataProvider('normalizeCases')]
    public function test_normalize_produces_the_canonical_structure(array $rawData, array $expected): void
    {
        $this->assertSame($expected, $this->transformer->normalize($rawData));
    }

    /** @return void */
    public function test_hydrate_returns_a_reading_with_typed_properties(): void
    {
        $reading = $this->transformer->hydrate([
            'temperature' => 22.5,
            'humidity'    => 65.0,
            'battery'     => 95,
            'rssi'        => -60,
        ]);

        $this->assertInstanceOf(HygrometerReading::class, $reading);
        $this->assertSame(22.5, $reading->temperature);
        $this->assertSame(65.0, $reading->humidity);
        $this->assertSame(95, $reading->battery);
        $this->assertSame(-60, $reading->rssi);
    }

    /** @return void */
    public function test_hydrate_allows_missing_battery_and_rssi(): void
    {
        $reading = $this->transformer->hydrate(['temperature' => 22.5, 'humidity' => 65.0]);

        $this->assertNull($reading->battery);
        $this->assertNull($reading->rssi);
    }

    /**
     * @param array<string, mixed> $rawData
     *
     * @return void
     */
    #[DataProvider('rawReadingCases')]
    public function test_hydrate_of_normalize_round_trips_the_input_values(array $rawData): void
    {
        $reading = $this->transformer->hydrate($this->transformer->normalize($rawData));

        $this->assertSame((float) $rawData['temperature'], $reading->temperature);
        $this->assertSame((float) $rawData['humidity'], $reading->humidity);
        $this->assertSame(isset($rawData['battery']) ? (int) $rawData['battery'] : null, $reading->battery);
        $this->assertSame(isset($rawData['rssi']) ? (int) $rawData['rssi'] : null, $reading->rssi);
    }

    /** @return void */
    public function test_to_api_values_converts_celsius_to_fahrenheit(): void
    {
        $reading = $this->transformer->hydrate(['temperature' => 22.5, 'humidity' => 65.0]);

        $this->assertSame(['temperature_f' => 72.5, 'humidity' => 65.0], $reading->toApiValues());
    }

    /** @return void */
    public function test_chart_fields_put_temperature_on_the_left_axis_and_humidity_on_the_right(): void
    {
        $fields = $this->transformer->chartFields();

        $this->assertCount(2, $fields);
        $this->assertSame('temperature_f', $fields[0]['key']);
        $this->assertSame('left', $fields[0]['axis']);
        $this->assertSame('humidity', $fields[1]['key']);
        $this->assertSame('right', $fields[1]['axis']);
    }
}
