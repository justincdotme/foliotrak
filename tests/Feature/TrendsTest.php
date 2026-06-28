<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Support\Trends;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TrendsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    public function test_light_returns_dated_value_pairs_from_light_level(): void
    {
        $plant = Plant::factory()->create();
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(3)]);
        Observation::factory()->create(['care_event_id' => $event->id, 'light_level' => 7]);

        $observations = $plant->careEvents()->with('observation')->get();

        $result = Trends::light($observations);

        $this->assertCount(1, $result);
        $this->assertSame('2026-06-23', $result[0]['date']);
        $this->assertSame(7, $result[0]['value']);
    }

    public function test_light_returns_null_value_when_observation_detail_row_is_absent(): void
    {
        $plant = Plant::factory()->create();
        // Event with no linked Observation row, simulating a partial save.
        CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(2)]);

        $observations = $plant->careEvents()->with('observation')->get();

        $result = Trends::light($observations);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['value']);
    }

    public function test_leaf_size_returns_dated_float_pairs_from_leaf_size_mm(): void
    {
        $plant = Plant::factory()->create();
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(5)]);
        Observation::factory()->create(['care_event_id' => $event->id, 'leaf_size_mm' => 45.5]);

        $observations = $plant->careEvents()->with('observation')->get();

        $result = Trends::leafSize($observations);

        $this->assertCount(1, $result);
        $this->assertSame('2026-06-21', $result[0]['date']);
        $this->assertSame(45.5, $result[0]['value']);
    }

    public function test_leaf_size_returns_null_when_leaf_size_is_not_recorded(): void
    {
        $plant = Plant::factory()->create();
        $event = CareEvent::factory()->ofType('observation')->for($plant)->create(['occurred_at' => now()->subDays(1)]);
        Observation::factory()->create(['care_event_id' => $event->id, 'leaf_size_mm' => null]);

        $observations = $plant->careEvents()->with('observation')->get();

        $result = Trends::leafSize($observations);

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['value']);
    }
}
