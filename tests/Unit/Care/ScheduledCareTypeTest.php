<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\Care\ScheduledCareType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduledCareTypeTest extends TestCase
{
    /** @return void */
    public function test_each_type_reads_its_own_override_events_and_start_date(): void
    {
        $plant = new Plant([
            'watering_interval_days_override'    => 7,
            'fertilizing_interval_days_override' => 30,
            'watering_schedule_start_date'       => '2026-06-01',
        ]);
        $watering    = new Collection([new CareEvent(['occurred_at' => Carbon::parse('2026-06-10')])]);
        $fertilizing = new Collection([new CareEvent(['occurred_at' => Carbon::parse('2026-06-20')])]);
        $plant->setRelation('wateringEvents', $watering);
        $plant->setRelation('fertilizingEvents', $fertilizing);

        $this->assertSame(7, ScheduledCareType::Watering->override($plant));
        $this->assertSame(30, ScheduledCareType::Fertilizing->override($plant));
        $this->assertTrue(ScheduledCareType::Watering->events($plant)->first()->occurred_at->eq('2026-06-10'));
        $this->assertTrue(ScheduledCareType::Fertilizing->events($plant)->first()->occurred_at->eq('2026-06-20'));
        $this->assertTrue(ScheduledCareType::Watering->scheduleStartDate($plant)->eq('2026-06-01'));
        $this->assertNull(ScheduledCareType::Fertilizing->scheduleStartDate($plant));
    }
}
