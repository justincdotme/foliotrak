<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Support\CorrelationEngine;
use App\Support\Correlation\PestResolutionFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PestResolutionFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_factor_key_and_outcome_key(): void
    {
        $factor = new PestResolutionFactor();

        $this->assertSame('resolution_time_days', $factor->key());
        $this->assertSame('health_at_clear', $factor->outcomeKey());
    }

    public function test_relations_include_the_symptoms_chain(): void
    {
        $this->assertContains('observationEvents.observation.symptoms', (new PestResolutionFactor())->relations());
    }

    public function test_resolved_episode_with_health_at_clear_produces_a_pair(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);

        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant, '2026-01-08', [], health: 5);
        $plant->load('observationEvents.observation.symptoms');

        $pairs = (new PestResolutionFactor())->pairs(collect([$plant]));

        $this->assertCount(1, $pairs);
        $this->assertSame(7.0, $pairs[0]['x']); // duration_days
        $this->assertSame(5.0, $pairs[0]['y']); // health_at_clear
    }

    public function test_open_episodes_do_not_produce_pairs(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);

        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant, '2026-01-08', [$symptom->id], health: 2);
        $plant->load('observationEvents.observation.symptoms');

        $this->assertSame([], (new PestResolutionFactor())->pairs(collect([$plant])));
    }

    public function test_resolved_episodes_without_health_at_clear_are_excluded(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);

        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant, '2026-01-08', [], health: null);
        $plant->load('observationEvents.observation.symptoms');

        $this->assertSame([], (new PestResolutionFactor())->pairs(collect([$plant])));
    }

    public function test_pairs_are_pooled_across_multiple_plants(): void
    {
        $symptom = Symptom::factory()->create(['category' => 'disease', 'key' => 'powdery_mildew', 'label' => 'Powdery mildew']);

        $plant1 = Plant::factory()->create();
        $this->observe($plant1, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant1, '2026-01-08', [], health: 5);
        $plant1->load('observationEvents.observation.symptoms');

        $plant2 = Plant::factory()->create();
        $this->observe($plant2, '2026-01-05', [$symptom->id], health: 2);
        $this->observe($plant2, '2026-01-19', [], health: 4);
        $plant2->load('observationEvents.observation.symptoms');

        $pairs = (new PestResolutionFactor())->pairs(collect([$plant1, $plant2]));

        $this->assertCount(2, $pairs);
        $xValues = array_column($pairs, 'x');
        $yValues = array_column($pairs, 'y');
        $this->assertContains(7.0, $xValues);
        $this->assertContains(14.0, $xValues);
        $this->assertContains(5.0, $yValues);
        $this->assertContains(4.0, $yValues);
    }

    public function test_factor_is_omitted_from_engine_output_when_fewer_than_five_resolved_episodes(): void
    {
        // One resolved episode -> one pair -> below CorrelationEngine::MIN_SAMPLES (5)
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);
        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant, '2026-01-08', [], health: 5);
        $plant->load('observationEvents.observation.symptoms');

        $result = CorrelationEngine::forPlants(collect([$plant]), [new PestResolutionFactor()]);

        $this->assertSame([], $result);
    }

    /**
     * @param  list<int>  $symptomIds
     */
    private function observe(Plant $plant, string $date, array $symptomIds, ?int $health = null): void
    {
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => $date]);
        $obs = Observation::factory()->create(['care_event_id' => $event->id, 'overall_health' => $health]);
        if ($symptomIds !== []) {
            $obs->symptoms()->attach($symptomIds);
        }
    }
}
