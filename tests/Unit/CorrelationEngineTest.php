<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Correlation\Factor;
use App\Support\CorrelationEngine;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CorrelationEngineTest extends TestCase
{
    public function test_a_strong_factor_is_reported_with_its_shape_and_passes_the_false_discovery_check(): void
    {
        $pairs = CorrelationEngine::forPlants(collect(), [self::monotonicFactor('strong')]);

        $this->assertCount(1, $pairs);
        $pair = $pairs[0];

        $this->assertSame('strong', $pair['x_variable']);
        $this->assertSame('overall_health', $pair['y_variable']);
        $this->assertSame(1.0, $pair['correlation']);
        $this->assertSame(6, $pair['sample_size']);
        $this->assertCount(6, $pair['points']);
        $this->assertTrue($pair['significant_after_fdr']);
        $this->assertArrayHasKey('lower', $pair['confidence_band']);
    }

    public function test_a_factor_below_the_minimum_sample_size_is_omitted(): void
    {
        $tiny = new class () implements Factor {
            public function key(): string
            {
                return 'tiny';
            }

            public function outcomeKey(): string
            {
                return 'overall_health';
            }

            public function relations(): array
            {
                return [];
            }

            public function pairs(Collection $plants): array
            {
                return [['x' => 1.0, 'y' => 1.0], ['x' => 2.0, 'y' => 2.0], ['x' => 3.0, 'y' => 3.0], ['x' => 4.0, 'y' => 4.0]];
            }
        };

        $this->assertSame([], CorrelationEngine::forPlants(collect(), [$tiny]));
    }

    public function test_false_discovery_control_separates_a_strong_pair_from_a_noisy_one(): void
    {
        $noisy = new class () implements Factor {
            public function key(): string
            {
                return 'noisy';
            }

            public function outcomeKey(): string
            {
                return 'overall_health';
            }

            public function relations(): array
            {
                return [];
            }

            public function pairs(Collection $plants): array
            {
                return [['x' => 1.0, 'y' => 3.0], ['x' => 2.0, 'y' => 1.0], ['x' => 3.0, 'y' => 5.0], ['x' => 4.0, 'y' => 2.0], ['x' => 5.0, 'y' => 4.0]];
            }
        };

        $pairs = CorrelationEngine::forPlants(collect(), [self::monotonicFactor('strong'), $noisy]);

        $this->assertCount(2, $pairs);
        $byKey = collect($pairs)->keyBy('x_variable');
        $this->assertTrue($byKey['strong']['significant_after_fdr']);
        $this->assertFalse($byKey['noisy']['significant_after_fdr']);
    }

    public function test_no_factors_or_no_samples_yields_no_pairs(): void
    {
        $this->assertSame([], CorrelationEngine::forPlants(collect(), []));
    }

    private static function monotonicFactor(string $key): Factor
    {
        return new class ($key) implements Factor {
            public function __construct(private string $name) {}

            public function key(): string
            {
                return $this->name;
            }

            public function outcomeKey(): string
            {
                return 'overall_health';
            }

            public function relations(): array
            {
                return [];
            }

            public function pairs(Collection $plants): array
            {
                return [
                    ['x' => 1.0, 'y' => 1.0], ['x' => 2.0, 'y' => 2.0], ['x' => 3.0, 'y' => 3.0],
                    ['x' => 4.0, 'y' => 4.0], ['x' => 5.0, 'y' => 5.0], ['x' => 6.0, 'y' => 6.0],
                ];
            }
        };
    }
}
