<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SoilMoistureLevel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SoilMoistureLevelTest extends TestCase
{
    /**
     * @return iterable<string, array{SoilMoistureLevel, float}>
     */
    public static function numericCases(): iterable
    {
        yield 'dry sits low on the 1-to-10 scale' => [SoilMoistureLevel::Dry, 2.0];
        yield 'moist sits mid-scale' => [SoilMoistureLevel::Moist, 5.0];
        yield 'wet sits high' => [SoilMoistureLevel::Wet, 8.0];
    }

    /**
     * @param SoilMoistureLevel $level
     * @param float             $expected
     *
     * @return void
     */
    #[DataProvider('numericCases')]
    public function test_maps_the_relative_reading_onto_the_shared_numeric_scale(SoilMoistureLevel $level, float $expected): void
    {
        $this->assertSame($expected, $level->numericValue());
    }
}
