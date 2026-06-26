<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PlantConditionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    /**
     * The condition is recomputed on read from the plant's latest observation. The
     * resolver's own precedence is unit-tested; this proves each input is wired
     * through from a real logged observation to the API Resource.
     */
    #[DataProvider('observationCases')]
    public function test_condition_reflects_the_latest_observation(
        ?int $health,
        ?string $symptomKey,
        string $expectedKey,
    ): void {
        $plant = Plant::factory()->create();
        $this->logObservation($plant, $health, '2026-06-22T18:00:00Z', $symptomKey);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.condition.key', $expectedKey);
    }

    /**
     * @return iterable<string, array{int|null, string|null, string}>
     */
    public static function observationCases(): iterable
    {
        yield 'high health reads healthy' => [5, null, 'healthy'];
        yield 'middling health reads fair' => [3, null, 'fair'];
        yield 'low health reads struggling' => [2, null, 'struggling'];
        yield 'a pest symptom reads infested' => [4, 'spider_mites', 'infested'];
        yield 'a disease symptom reads diseased' => [4, 'powdery_mildew', 'diseased'];
        yield 'leaf damage reads leaf stress' => [5, 'leaf_spots', 'burnt'];
        yield 'null health reads as no reading' => [null, null, 'unknown'];
    }

    public function test_the_most_recent_observation_wins(): void
    {
        $plant = Plant::factory()->create();
        $this->logObservation($plant, 5, '2026-06-01T18:00:00Z');
        $this->logObservation($plant, 2, '2026-06-20T18:00:00Z');

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.condition.key', 'struggling');
    }

    public function test_an_overdue_watering_reads_as_likely_dry_when_an_override_is_set(): void
    {
        $plant = Plant::factory()->create(['watering_interval_days_override' => 7]);
        CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays(30)]);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.condition.key', 'dry');
    }

    public function test_an_overdue_watering_reads_as_likely_dry_from_the_derived_interval(): void
    {
        // No override, but a steady 7-day rhythm with the last watering long past it.
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);
        foreach ([44, 37, 30] as $daysAgo) {
            CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays($daysAgo)]);
        }

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.condition.key', 'dry');
    }

    public function test_a_single_watering_cannot_derive_an_interval_so_it_does_not_read_as_dry(): void
    {
        // One event forms no gap, and there is no override, so no interval can be derived.
        $plant = Plant::factory()->create(['watering_interval_days_override' => null]);
        CareEvent::factory()->ofType('watering')->for($plant)->create(['occurred_at' => now()->subDays(30)]);

        $this->getJson("/api/plants/{$plant->id}")
            ->assertOk()
            ->assertJsonPath('data.condition.key', 'unknown');
    }

    private function logObservation(Plant $plant, ?int $health, string $occurredAt, ?string $symptomKey = null): void
    {
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => $occurredAt]);
        $observation = Observation::factory()->create(['care_event_id' => $event->id, 'overall_health' => $health]);

        if ($symptomKey !== null) {
            $observation->symptoms()->attach(Symptom::where('key', $symptomKey)->value('id'));
        }
    }
}
