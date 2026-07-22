<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Models\CareEvent;
use App\Models\Plant;
use App\Support\Care\CareSchedule;
use App\Support\Care\DueStatus;
use App\Support\Care\ScheduledCareType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CareScheduleTest extends TestCase
{
    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-06-26 09:00:00'));
    }

    /**
     * @return iterable<string, array{list<string>, int|null}>
     */
    public static function medianCases(): iterable
    {
        // A median needs at least one gap, so fewer than two events is undefined.
        yield 'no events' => [[], null];
        yield 'a single event has no gap' => [['2026-01-01'], null];

        yield 'two events is the one gap' => [['2026-01-01', '2026-01-08'], 7];

        // Odd gap count takes the middle; even averages the two middle gaps.
        yield 'odd gap count takes the middle' => [['2026-01-01', '2026-01-04', '2026-01-09', '2026-01-20'], 5];
        yield 'even gap count averages the middle pair' => [['2026-01-01', '2026-01-05', '2026-01-11', '2026-01-19', '2026-01-29'], 7];

        // The interval is whole days; a fractional median rounds.
        yield 'a fractional median rounds to the nearest day' => [['2026-01-01', '2026-01-07', '2026-01-14'], 7];

        // Sub-day or zero gaps never produce a zero interval that reads perpetually due.
        yield 'a sub-day gap floors at one day' => [['2026-01-01 00:00:00', '2026-01-01 12:00:00'], 1];
        yield 'an identical timestamp floors at one day' => [['2026-01-01 09:00:00', '2026-01-01 09:00:00'], 1];

        // Unsorted input is tolerated so callers need not pre-sort.
        yield 'unsorted input is sorted before measuring' => [['2026-01-15', '2026-01-01', '2026-01-08'], 7];
    }

    /**
     * @param list<string> $occurredAt
     * @param integer|null $expected
     *
     * @return void
     */
    #[DataProvider('medianCases')]
    public function test_derives_the_median_gap_in_whole_days(array $occurredAt, ?int $expected): void
    {
        $dates = array_map(fn (string $value): Carbon => Carbon::parse($value), $occurredAt);

        $this->assertSame($expected, CareSchedule::medianGapDays($dates));
    }

    /** @return void */
    public function test_no_schedule_below_the_28_day_gate_without_an_override(): void
    {
        // Three waterings in a clean 7-day rhythm, but the first is only 22 days
        // old: the median may not fire until 28 days of history exist (FOL-98).
        $plant = $this->plantWateredDaysAgo(null, 22, 15, 8);

        $this->assertNull(CareSchedule::for($plant, ScheduledCareType::Watering));
    }

    /** @return void */
    public function test_the_median_fires_once_the_first_event_is_28_days_old(): void
    {
        $schedule = CareSchedule::for($this->plantWateredDaysAgo(null, 28, 21, 14), ScheduledCareType::Watering);

        $this->assertNotNull($schedule);
        $this->assertSame(7, $schedule->intervalDays);
    }

    /** @return void */
    public function test_an_override_wins_below_the_gate(): void
    {
        $schedule = CareSchedule::for($this->plantWateredDaysAgo(5, 8), ScheduledCareType::Watering);

        $this->assertNotNull($schedule);
        $this->assertSame(5, $schedule->intervalDays);
    }

    /** @return void */
    public function test_an_override_wins_over_a_gated_median(): void
    {
        $schedule = CareSchedule::for($this->plantWateredDaysAgo(3, 36, 29, 22, 15, 8), ScheduledCareType::Watering);

        $this->assertSame(3, $schedule?->intervalDays);
    }

    /** @return void */
    public function test_no_schedule_without_an_interval_or_without_an_anchor(): void
    {
        // One event forms no gap and there is no override.
        $this->assertNull(CareSchedule::for($this->plantWateredDaysAgo(null, 40), ScheduledCareType::Watering));

        // An override with no events and no start date has nothing to count from.
        $this->assertNull(CareSchedule::for($this->plantWateredDaysAgo(7), ScheduledCareType::Watering));
    }

    /** @return void */
    public function test_the_start_date_anchors_a_schedule_with_no_events(): void
    {
        $plant = new Plant([
            'watering_interval_days_override' => 7,
            'watering_schedule_start_date'    => '2026-06-24',
        ]);
        $plant->setRelation('wateringEvents', new Collection);
        $plant->setRelation('fertilizingEvents', new Collection);

        $due = CareSchedule::for($plant, ScheduledCareType::Watering)?->due();

        $this->assertSame('2026-07-01', $due?->dueDate->format('Y-m-d'));
        $this->assertSame(5, $due?->daysLeft);
        $this->assertSame(DueStatus::Ok, $due?->status);
    }

    /** @return void */
    public function test_due_counts_midnight_normalized_calendar_days(): void
    {
        // Watered 5.5 days ago on a 7-day override: the due moment is 20:30
        // tomorrow, which is one calendar day away regardless of clock time.
        $plant = new Plant(['watering_interval_days_override' => 7]);
        $plant->setRelation('wateringEvents', new Collection([
            new CareEvent(['occurred_at' => now()->subDays(5)->subHours(12)]),
        ]));
        $plant->setRelation('fertilizingEvents', new Collection);

        $due = CareSchedule::for($plant, ScheduledCareType::Watering)?->due();

        $this->assertSame('2026-06-27', $due?->dueDate->format('Y-m-d'));
        $this->assertSame(1, $due?->daysLeft);
        $this->assertSame(DueStatus::DueSoon, $due?->status);
    }

    /** @return void */
    public function test_an_overdue_schedule_reports_negative_days_left(): void
    {
        $due = CareSchedule::for($this->plantWateredDaysAgo(null, 36, 29, 22, 15, 8), ScheduledCareType::Watering)?->due();

        $this->assertSame(7, $due?->intervalDays);
        $this->assertSame('2026-06-25', $due?->dueDate->format('Y-m-d'));
        $this->assertSame(-1, $due?->daysLeft);
        $this->assertSame(DueStatus::Overdue, $due?->status);
        $this->assertTrue($due !== null && $due->isDue());
        $this->assertSame(1, $due?->daysOverdue());
    }

    /**
     * @param integer|null $override
     * @param integer      ...$daysAgo
     *
     * @return Plant
     */
    private function plantWateredDaysAgo(?int $override, int ...$daysAgo): Plant
    {
        $plant = new Plant(['watering_interval_days_override' => $override]);
        $plant->setRelation('wateringEvents', new Collection(array_map(
            fn (int $days): CareEvent => new CareEvent(['occurred_at' => now()->subDays($days)]),
            $daysAgo,
        )));
        $plant->setRelation('fertilizingEvents', new Collection);

        return $plant;
    }
}
