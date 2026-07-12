<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sensors;

use App\Services\Sensors\Transformers\LuxTransformer;
use App\Services\Sensors\ValueObjects\LuxReading;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LuxTransformerTest extends TestCase
{
    /** @var LuxTransformer */
    private LuxTransformer $transformer;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new LuxTransformer;
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'full data keeps all fields' => [
            ['lux' => 34.38, 'white' => 827, 'als' => 597, 'rssi' => -66],
            ['lux' => 34.38, 'white' => 827, 'als' => 597, 'rssi' => -66],
        ];
        yield 'lux only omits absent fields' => [
            ['lux' => 100.5],
            ['lux' => 100.5],
        ];
        yield 'null battery is stripped' => [
            ['lux' => 50.0, 'white' => 200, 'als' => 180, 'battery' => null, 'rssi' => -70],
            ['lux' => 50.0, 'white' => 200, 'als' => 180, 'rssi' => -70],
        ];
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function roundTripCases(): iterable
    {
        yield 'full data' => [['lux' => 34.38, 'white' => 827, 'als' => 597, 'rssi' => -66]];
        yield 'lux only' => [['lux' => 100.5]];
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
    public function test_normalize_rejects_readings_missing_lux(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transformer->normalize(['white' => 827, 'als' => 597]);
    }

    /** @return void */
    public function test_hydrate_returns_a_reading_with_typed_properties(): void
    {
        $reading = $this->transformer->hydrate([
            'lux'   => 34.38,
            'white' => 827,
            'als'   => 597,
            'rssi'  => -66,
        ]);

        $this->assertInstanceOf(LuxReading::class, $reading);
        $this->assertSame(34.38, $reading->lux);
        $this->assertSame(827, $reading->white);
        $this->assertSame(597, $reading->als);
        $this->assertSame(-66, $reading->rssi);
    }

    /** @return void */
    public function test_hydrate_defaults_absent_raw_channels_to_zero(): void
    {
        $reading = $this->transformer->hydrate(['lux' => 50.0]);

        $this->assertSame(0, $reading->white);
        $this->assertSame(0, $reading->als);
        $this->assertNull($reading->rssi);
    }

    /**
     * @param array<string, mixed> $rawData
     *
     * @return void
     */
    #[DataProvider('roundTripCases')]
    public function test_hydrate_of_normalize_round_trips_the_lux_value(array $rawData): void
    {
        $reading = $this->transformer->hydrate($this->transformer->normalize($rawData));

        $this->assertSame((float) $rawData['lux'], $reading->lux);
    }

    /** @return void */
    public function test_to_api_values_returns_rounded_lux(): void
    {
        $reading = $this->transformer->hydrate(['lux' => 34.38, 'white' => 827, 'als' => 597]);

        $this->assertSame(['lux' => 34.4], $reading->toApiValues());
    }

    /** @return void */
    public function test_chart_fields_provides_a_single_lux_field_on_its_own_axis(): void
    {
        $fields = $this->transformer->chartFields();

        $this->assertCount(1, $fields);
        $this->assertSame('lux', $fields[0]['key']);
        $this->assertSame('lux', $fields[0]['axis']);
        $this->assertSame(' lx', $fields[0]['unit']);
    }
}
