<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SoilMoistureLevel;
use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Support\Correlation\HumidityFactor;
use App\Support\Correlation\LightLevelFactor;
use App\Support\Correlation\SoilMoistureFactor;
use App\Support\CorrelationEngine;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorrelationFactorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareLookupSeeder::class);
    }

    public function test_humidity_factor_emits_pairs_from_ambient_humidity_and_health(): void
    {
        $plant = Plant::factory()->create();

        // Five valid observations plus two that should be skipped (null fields).
        $fixtures = [[40, 1], [50, 2], [60, 3], [70, 4], [80, 5]];
        foreach ($fixtures as $i => [$humidity, $health]) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => $health,
                'ambient_humidity_pct' => $humidity,
            ]);
        }

        // Null humidity: should be skipped.
        $nullHumidity = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(2)]);
        Observation::factory()->create([
            'care_event_id' => $nullHumidity->id,
            'overall_health' => 3,
            'ambient_humidity_pct' => null,
        ]);

        // Null health: should be skipped.
        $nullHealth = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(1)]);
        Observation::factory()->create([
            'care_event_id' => $nullHealth->id,
            'overall_health' => null,
            'ambient_humidity_pct' => 55,
        ]);

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);
        $result = CorrelationEngine::forPlants(collect([$loaded]), [new HumidityFactor]);

        $this->assertCount(1, $result);
        $pair = $result[0];
        $this->assertSame('ambient_humidity_pct', $pair['x_variable']);
        $this->assertSame('overall_health', $pair['y_variable']);
        $this->assertSame(5, $pair['sample_size']);

        $points = collect($pair['points'])->sortBy('x')->values();
        foreach ($fixtures as $idx => [$humidity, $health]) {
            $this->assertSame((float) $humidity, $points[$idx]['x']);
            $this->assertSame((float) $health, $points[$idx]['y']);
        }
    }

    public function test_light_level_factor_emits_pairs_from_light_level_and_health(): void
    {
        $plant = Plant::factory()->create();

        $fixtures = [[1, 5], [3, 4], [5, 3], [7, 2], [9, 1]];
        foreach ($fixtures as $i => [$lightLevel, $health]) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => $health,
                'light_level' => $lightLevel,
            ]);
        }

        // Null light_level: should be skipped.
        $nullLight = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(2)]);
        Observation::factory()->create([
            'care_event_id' => $nullLight->id,
            'overall_health' => 3,
            'light_level' => null,
        ]);

        // Null health: should be skipped.
        $nullHealth = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(1)]);
        Observation::factory()->create([
            'care_event_id' => $nullHealth->id,
            'overall_health' => null,
            'light_level' => 5,
        ]);

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);
        $result = CorrelationEngine::forPlants(collect([$loaded]), [new LightLevelFactor]);

        $this->assertCount(1, $result);
        $pair = $result[0];
        $this->assertSame('light_level', $pair['x_variable']);
        $this->assertSame('overall_health', $pair['y_variable']);
        $this->assertSame(5, $pair['sample_size']);

        $points = collect($pair['points'])->sortBy('x')->values();
        foreach ($fixtures as $idx => [$lightLevel, $health]) {
            $this->assertSame((float) $lightLevel, $points[$idx]['x']);
            $this->assertSame((float) $health, $points[$idx]['y']);
        }
    }

    public function test_soil_moisture_factor_uses_precise_value_when_present(): void
    {
        $plant = Plant::factory()->create();

        // soil_moisture_precise takes priority over soil_moisture_relative.
        $fixtures = [[1, 5], [3, 4], [5, 3], [7, 2], [9, 1]];
        foreach ($fixtures as $i => [$precise, $health]) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => $health,
                'soil_moisture_precise' => $precise,
                'soil_moisture_relative' => SoilMoistureLevel::Wet, // present but must be ignored
            ]);
        }

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);
        $result = CorrelationEngine::forPlants(collect([$loaded]), [new SoilMoistureFactor]);

        $this->assertCount(1, $result);
        $pair = $result[0];
        $this->assertSame('soil_moisture', $pair['x_variable']);
        $this->assertSame('overall_health', $pair['y_variable']);
        $this->assertSame(5, $pair['sample_size']);

        $points = collect($pair['points'])->sortBy('x')->values();
        foreach ($fixtures as $idx => [$precise, $health]) {
            $this->assertSame((float) $precise, $points[$idx]['x']);
            $this->assertSame((float) $health, $points[$idx]['y']);
        }
    }

    public function test_soil_moisture_factor_falls_back_to_relative_enum_mapping_when_precise_is_null(): void
    {
        $plant = Plant::factory()->create();

        // dry=>2.0, moist=>5.0, wet=>8.0. Five observations using only the relative field.
        $fixtures = [
            [SoilMoistureLevel::Dry, 2.0, 5],
            [SoilMoistureLevel::Moist, 5.0, 4],
            [SoilMoistureLevel::Wet, 8.0, 3],
            [SoilMoistureLevel::Dry, 2.0, 2],
            [SoilMoistureLevel::Moist, 5.0, 1],
        ];

        foreach ($fixtures as $i => [$relative, , $health]) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => $health,
                'soil_moisture_precise' => null,
                'soil_moisture_relative' => $relative,
            ]);
        }

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);
        $result = CorrelationEngine::forPlants(collect([$loaded]), [new SoilMoistureFactor]);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['sample_size']);

        // Sort by y (health) descending to align with fixture order by occurred_at.
        $points = collect($result[0]['points'])->sortByDesc('y')->values();
        foreach ($fixtures as $idx => [, $expectedX, $health]) {
            $this->assertSame($expectedX, $points[$idx]['x']);
            $this->assertSame((float) $health, $points[$idx]['y']);
        }
    }

    public function test_soil_moisture_factor_skips_observations_with_both_moisture_values_null(): void
    {
        $plant = Plant::factory()->create();

        // Five valid observations with soil_moisture_precise set.
        for ($i = 0; $i < 5; $i++) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => 3,
                'soil_moisture_precise' => 5,
                'soil_moisture_relative' => null,
            ]);
        }

        // Two observations with both moisture fields null: should be skipped.
        for ($i = 0; $i < 2; $i++) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays($i + 1),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => 4,
                'soil_moisture_precise' => null,
                'soil_moisture_relative' => null,
            ]);
        }

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);
        $result = CorrelationEngine::forPlants(collect([$loaded]), [new SoilMoistureFactor]);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['sample_size']);
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function belowMinimumSampleFactors(): array
    {
        return [
            'humidity' => [HumidityFactor::class],
            'light_level' => [LightLevelFactor::class],
            'soil_moisture' => [SoilMoistureFactor::class],
        ];
    }

    /**
     * @param  class-string  $factorClass
     */
    #[DataProvider('belowMinimumSampleFactors')]
    public function test_factor_is_omitted_when_sample_count_is_below_minimum(string $factorClass): void
    {
        $plant = Plant::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $event = CareEvent::factory()->ofType('observation')->for($plant)->create([
                'occurred_at' => now()->subDays(10 - $i),
            ]);
            Observation::factory()->create([
                'care_event_id' => $event->id,
                'overall_health' => 3,
                'ambient_humidity_pct' => 55,
                'light_level' => 6,
                'soil_moisture_precise' => 5,
            ]);
        }

        $loaded = Plant::with(['observationEvents.observation'])->findOrFail($plant->id);

        $this->assertSame([], CorrelationEngine::forPlants(collect([$loaded]), [new $factorClass]));
    }
}
