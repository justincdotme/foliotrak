<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\Care\CareDue;
use App\Support\Care\DueStatus;
use App\Support\Care\ScheduledCareType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CareDueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $wateringDaysAgo
     * @param  list<int>  $fertilizingDaysAgo
     */
    private function plant(array $attributes, array $wateringDaysAgo = [], array $fertilizingDaysAgo = []): Plant
    {
        $plant = new Plant($attributes);
        $plant->setRelation('wateringEvents', new Collection(array_map(
            fn (int $days): CareEvent => new CareEvent(['occurred_at' => now()->subDays($days)]),
            $wateringDaysAgo,
        )));
        $plant->setRelation('fertilizingEvents', new Collection(array_map(
            fn (int $days): CareEvent => new CareEvent(['occurred_at' => now()->subDays($days)]),
            $fertilizingDaysAgo,
        )));

        return $plant;
    }

    public function test_for_plant_lists_watering_then_fertilizing(): void
    {
        $plant = $this->plant(
            ['watering_interval_days_override' => 7, 'fertilizing_interval_days_override' => 30],
            [8],
            [10],
        );

        $dues = CareDue::forPlant($plant);

        $this->assertCount(2, $dues);
        $this->assertSame(
            [ScheduledCareType::Watering, ScheduledCareType::Fertilizing],
            array_map(fn (CareDue $due): ScheduledCareType => $due->type, $dues),
        );
    }

    public function test_for_plant_drops_types_without_a_schedule_and_reindexes(): void
    {
        // Only fertilizing is derivable; the missing watering entry must not
        // leave a gap in the list keys.
        $plant = $this->plant(['fertilizing_interval_days_override' => 30], [], [10]);

        $dues = CareDue::forPlant($plant);

        $this->assertCount(1, $dues);
        $this->assertArrayHasKey(0, $dues);
        $this->assertSame(ScheduledCareType::Fertilizing, $dues[0]->type);
    }

    public function test_for_returns_null_without_a_schedule(): void
    {
        $this->assertNull(CareDue::for($this->plant([]), ScheduledCareType::Watering));
    }

    public function test_due_exactly_today_is_due(): void
    {
        $due = CareDue::for($this->plant(['watering_interval_days_override' => 7], [7]), ScheduledCareType::Watering);

        $this->assertSame(0, $due?->daysLeft);
        $this->assertSame(DueStatus::DueSoon, $due?->status);
        $this->assertTrue($due !== null && $due->isDue());
        $this->assertSame(0, $due?->daysOverdue());
    }
}
