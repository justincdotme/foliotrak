<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Support\SymptomEpisodeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SymptomEpisodeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_plant_with_no_observations_returns_empty_episodes(): void
    {
        $plant = Plant::factory()->create();
        $plant->load('observationEvents.observation.symptoms');

        $this->assertSame([], SymptomEpisodeResolver::forPlant($plant));
    }

    public function test_plant_with_one_observation_returns_empty_episodes(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);
        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);

        $plant->load('observationEvents.observation.symptoms');

        $this->assertSame([], SymptomEpisodeResolver::forPlant($plant));
    }

    public function test_symptom_that_appears_then_clears_produces_a_resolved_episode(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);

        $this->observe($plant, '2026-01-01', [$symptom->id], health: 3);
        $this->observe($plant, '2026-01-08', [], health: 5);

        $plant->load('observationEvents.observation.symptoms');
        $episodes = SymptomEpisodeResolver::forPlant($plant);

        $this->assertCount(1, $episodes);
        $episode = $episodes[0];
        $this->assertSame('spider_mites', $episode['symptom_key']);
        $this->assertSame('Spider mites', $episode['symptom_label']);
        $this->assertSame('pest', $episode['category']);
        $this->assertSame('2026-01-01', $episode['appeared_at']);
        $this->assertSame('2026-01-08', $episode['cleared_at']);
        $this->assertSame(7, $episode['duration_days']);
    }

    public function test_symptom_present_on_all_observations_is_an_open_episode(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'leaf', 'key' => 'yellow_leaf', 'label' => 'Yellowing leaves']);

        $this->observe($plant, '2026-01-01', [$symptom->id]);
        $this->observe($plant, '2026-01-15', [$symptom->id]);

        $plant->load('observationEvents.observation.symptoms');
        $episodes = SymptomEpisodeResolver::forPlant($plant);

        $this->assertCount(1, $episodes);
        $episode = $episodes[0];
        $this->assertSame('yellow_leaf', $episode['symptom_key']);
        $this->assertSame('2026-01-01', $episode['appeared_at']);
        $this->assertNull($episode['cleared_at']);
        $this->assertNull($episode['duration_days']);
    }

    public function test_multiple_symptoms_are_tracked_independently(): void
    {
        $plant = Plant::factory()->create();
        $mites = Symptom::factory()->create(['category' => 'pest', 'key' => 'spider_mites', 'label' => 'Spider mites']);
        $gnats = Symptom::factory()->create(['category' => 'pest', 'key' => 'fungus_gnats', 'label' => 'Fungus gnats']);
        $mildew = Symptom::factory()->create(['category' => 'disease', 'key' => 'powdery_mildew', 'label' => 'Powdery mildew']);

        // obs1: mites and gnats; obs2: gnats and mildew (mites cleared, mildew appeared)
        $this->observe($plant, '2026-01-01', [$mites->id, $gnats->id]);
        $this->observe($plant, '2026-01-10', [$gnats->id, $mildew->id]);

        $plant->load('observationEvents.observation.symptoms');
        $episodes = SymptomEpisodeResolver::forPlant($plant);

        $this->assertCount(3, $episodes);

        $byKey = collect($episodes)->keyBy('symptom_key');

        $this->assertSame('2026-01-01', $byKey['spider_mites']['appeared_at']);
        $this->assertSame('2026-01-10', $byKey['spider_mites']['cleared_at']);
        $this->assertSame(9, $byKey['spider_mites']['duration_days']);

        $this->assertSame('2026-01-01', $byKey['fungus_gnats']['appeared_at']);
        $this->assertNull($byKey['fungus_gnats']['cleared_at']);

        $this->assertSame('2026-01-10', $byKey['powdery_mildew']['appeared_at']);
        $this->assertNull($byKey['powdery_mildew']['cleared_at']);
    }

    public function test_symptom_that_reappears_creates_two_separate_episodes(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'pest', 'key' => 'mealybugs', 'label' => 'Mealybugs']);

        $this->observe($plant, '2026-01-01', [$symptom->id]);
        $this->observe($plant, '2026-01-10', []);
        $this->observe($plant, '2026-01-20', [$symptom->id]);

        $plant->load('observationEvents.observation.symptoms');
        $episodes = SymptomEpisodeResolver::forPlant($plant);

        $this->assertCount(2, $episodes);

        $resolved = collect($episodes)->firstWhere('cleared_at', '!==', null);
        $open = collect($episodes)->firstWhere('cleared_at', null);

        $this->assertNotNull($resolved);
        $this->assertSame('2026-01-01', $resolved['appeared_at']);
        $this->assertSame('2026-01-10', $resolved['cleared_at']);
        $this->assertSame(9, $resolved['duration_days']);

        $this->assertNotNull($open);
        $this->assertSame('2026-01-20', $open['appeared_at']);
        $this->assertNull($open['cleared_at']);
    }

    public function test_health_at_appear_and_clear_are_captured(): void
    {
        $plant = Plant::factory()->create();
        $symptom = Symptom::factory()->create(['category' => 'root', 'key' => 'root_rot', 'label' => 'Root rot']);

        $this->observe($plant, '2026-01-01', [$symptom->id], health: 2);
        $this->observe($plant, '2026-01-14', [], health: 4);

        $plant->load('observationEvents.observation.symptoms');
        $episodes = SymptomEpisodeResolver::forPlant($plant);

        $this->assertCount(1, $episodes);
        $this->assertSame(2, $episodes[0]['health_at_appear']);
        $this->assertSame(4, $episodes[0]['health_at_clear']);
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
