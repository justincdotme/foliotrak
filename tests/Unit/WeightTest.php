<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Weight;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WeightTest extends TestCase
{
    /**
     * @return iterable<string, array{float, float, float, int}>
     */
    public static function componentCases(): iterable
    {
        yield 'grams only pass through' => [0.0, 0.0, 250.0, 250];
        yield 'a pound rounds to the nearest gram' => [1.0, 0.0, 0.0, 454];
        yield 'an ounce rounds to the nearest gram' => [0.0, 1.0, 0.0, 28];
        yield 'mixed components sum and round' => [2.0, 3.0, 5.0, 997];
        yield 'empty is zero' => [0.0, 0.0, 0.0, 0];
    }

    /**
     * @return iterable<string, array{int, array{lb: int, oz: int, g: float}}>
     */
    public static function decompositionCases(): iterable
    {
        yield 'under an ounce stays in grams' => [20, ['lb' => 0, 'oz' => 0, 'g' => 20.0]];
        yield 'grams spill into ounces' => [250, ['lb' => 0, 'oz' => 8, 'g' => 23.2]];
        yield 'a pound with a small remainder' => [454, ['lb' => 1, 'oz' => 0, 'g' => 0.4]];
        yield 'a kilo splits across all three' => [1000, ['lb' => 2, 'oz' => 3, 'g' => 7.8]];
        yield 'zero is empty' => [0, ['lb' => 0, 'oz' => 0, 'g' => 0.0]];
    }

    /**
     * Inbound: lb/oz/g sum to canonical grams, rounded to the nearest gram with
     * the same factors the SPA uses, so the wire value matches the client's math.
     *
     * @param float   $lb
     * @param float   $oz
     * @param float   $g
     * @param integer $expected
     *
     * @return void
     */
    #[DataProvider('componentCases')]
    public function test_sums_components_to_grams(float $lb, float $oz, float $g, int $expected): void
    {
        $this->assertSame($expected, Weight::fromComponents($lb, $oz, $g)->grams);
    }

    /**
     * Outbound: grams decompose greedily into lb, then oz, then a 0.1g remainder,
     * matching the prototype's gramsToWeight so the form shows the same split.
     *
     * @param integer                           $grams
     * @param array{lb: int, oz: int, g: float} $expected
     *
     * @return void
     */
    #[DataProvider('decompositionCases')]
    public function test_decomposes_grams_into_components(int $grams, array $expected): void
    {
        $this->assertSame($expected, Weight::fromGrams($grams)->toComponents());
    }
}
