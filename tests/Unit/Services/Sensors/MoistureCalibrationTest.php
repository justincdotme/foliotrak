<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Sensors;

use App\Services\Sensors\MoistureCalibration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoistureCalibrationTest extends TestCase
{
    /**
     * @return iterable<string, array{float, list<array{position: int, value: int}>, int|null}>
     */
    public static function scaleCases(): iterable
    {
        $anchors = [
            ['position' => 1, 'value' => 3100],
            ['position' => 5, 'value' => 2200],
            ['position' => 10, 'value' => 1300],
        ];

        yield 'wetter than the wettest anchor clamps to 10' => [1000.0, $anchors, 10];
        yield 'drier than the driest anchor clamps to 1' => [3500.0, $anchors, 1];
        yield 'exact anchor value returns its position' => [2200.0, $anchors, 5];
        yield 'midway on the dry segment interpolates' => [2650.0, $anchors, 3];
        yield 'interpolates on the wet segment' => [1480.0, $anchors, 9];
        yield 'two anchors interpolate linearly' => [
            2200.0,
            [['position' => 1, 'value' => 3100], ['position' => 10, 'value' => 1300]],
            6,
        ];
        yield 'single anchor cannot scale' => [2200.0, [['position' => 5, 'value' => 2200]], null];
        yield 'no anchors cannot scale' => [2200.0, [], null];
        yield 'duplicate-value anchors resolve to the boundary position' => [
            2200.0,
            [['position' => 4, 'value' => 2200], ['position' => 6, 'value' => 2200]],
            4,
        ];
    }

    /**
     * @param float                                  $rawValue
     * @param list<array{position: int, value: int}> $points
     * @param integer|null                           $expected
     *
     * @return void
     */
    #[DataProvider('scaleCases')]
    public function test_scale_maps_raw_values_onto_the_meter(float $rawValue, array $points, ?int $expected): void
    {
        $this->assertSame($expected, MoistureCalibration::scale($rawValue, $points));
    }
}
