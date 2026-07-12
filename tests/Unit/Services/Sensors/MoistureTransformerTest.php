<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sensors;

use App\Services\Sensors\Transformers\MoistureTransformer;
use App\Services\Sensors\ValueObjects\MoistureReading;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoistureTransformerTest extends TestCase
{
    /** @var MoistureTransformer */
    private MoistureTransformer $transformer;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new MoistureTransformer;
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function normalizeCases(): iterable
    {
        yield 'full data keeps all fields' => [
            ['moisture' => 2975, 'battery' => 3700, 'rssi' => -75],
            ['moisture' => 2975, 'battery' => 3700, 'rssi' => -75],
        ];
        yield 'moisture only omits absent fields' => [
            ['moisture' => 1450],
            ['moisture' => 1450],
        ];
        yield 'null battery is stripped' => [
            ['moisture' => 2975, 'battery' => null, 'rssi' => -75],
            ['moisture' => 2975, 'rssi' => -75],
        ];
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
    public function test_normalize_rejects_readings_missing_moisture(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transformer->normalize(['battery' => 3700, 'rssi' => -75]);
    }

    /** @return void */
    public function test_hydrate_returns_a_reading_with_typed_properties(): void
    {
        $reading = $this->transformer->hydrate(['moisture' => 2975, 'battery' => 3700, 'rssi' => -75]);

        $this->assertInstanceOf(MoistureReading::class, $reading);
        $this->assertSame(2975, $reading->moisture);
        $this->assertSame(3700, $reading->battery);
        $this->assertSame(-75, $reading->rssi);
    }

    /** @return void */
    public function test_hydrate_defaults_absent_optionals_to_null(): void
    {
        $reading = $this->transformer->hydrate(['moisture' => 1450]);

        $this->assertNull($reading->battery);
        $this->assertNull($reading->rssi);
    }

    /** @return void */
    public function test_to_api_values_returns_the_raw_adc_count(): void
    {
        $reading = $this->transformer->hydrate(['moisture' => 2975, 'rssi' => -75]);

        $this->assertSame(['moisture' => 2975], $reading->toApiValues());
    }

    /** @return void */
    public function test_chart_fields_provides_a_single_moisture_field_on_its_own_axis(): void
    {
        $fields = $this->transformer->chartFields();

        $this->assertCount(1, $fields);
        $this->assertSame('moisture', $fields[0]['key']);
        $this->assertSame('moisture', $fields[0]['axis']);
        $this->assertSame('Soil Moisture', $fields[0]['label']);
    }
}
