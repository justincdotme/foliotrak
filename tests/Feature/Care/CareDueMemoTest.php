<?php

declare(strict_types=1);

namespace Tests\Feature\Care;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use App\Support\Care\CareDue;
use App\Support\Care\ScheduledCareType;
use App\Support\Care\SoilHistory;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CareDueMemoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
        $this->travelTo(Carbon::parse('2026-08-22 12:00:00'));
    }

    /**
     * PlantController::present() asks for the condition and the due list on
     * one line, and both reach the watering schedule. Deriving it twice doubles
     * the cost of every plant on the page.
     *
     * @return void
     */
    public function test_the_watering_schedule_is_derived_once_per_plant(): void
    {
        $plant = $this->plantWithWateringHistory();

        $plant->condition();
        $first = CareDue::for($plant, ScheduledCareType::Watering);

        $this->assertNotNull($first);
        $this->assertSame($first, CareDue::for($plant, ScheduledCareType::Watering));
        $this->assertSame($first, CareDue::forPlant($plant)[0]);
    }

    /**
     * A plant with no derivable schedule must not retry on every call either.
     *
     * @return void
     */
    public function test_an_absent_schedule_is_remembered_too(): void
    {
        $plant = Plant::factory()->create()->fresh(SoilHistory::RELATIONS);

        $this->assertNull($plant->careDue(ScheduledCareType::Watering));
        $this->assertNull($plant->careDue(ScheduledCareType::Watering));
        $this->assertSame([], CareDue::forPlant($plant));
    }

    /**
     * @return Plant
     */
    private function plantWithWateringHistory(): Plant
    {
        $plant = Plant::factory()->create();

        foreach ([40, 33, 26, 19, 12, 5] as $daysAgo) {
            CareEvent::create([
                'plant_id'           => $plant->id,
                'care_event_type_id' => CareEventType::where('key', 'watering')->value('id'),
                'occurred_at'        => Carbon::now()->subDays($daysAgo),
            ]);
        }

        return $plant->fresh(SoilHistory::RELATIONS);
    }
}
