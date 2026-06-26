<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CareScheduleResolver;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CareScheduleResolverTest extends TestCase
{
    /**
     * @param  list<string>  $occurredAt
     */
    #[DataProvider('medianCases')]
    public function test_derives_the_median_gap_in_whole_days(array $occurredAt, ?int $expected): void
    {
        $dates = array_map(fn (string $value): Carbon => Carbon::parse($value), $occurredAt);

        $this->assertSame($expected, CareScheduleResolver::medianGapDays($dates));
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

    public function test_interval_for_type_prefers_the_manual_override(): void
    {
        $weeklyGaps = [Carbon::parse('2026-01-01'), Carbon::parse('2026-01-08'), Carbon::parse('2026-01-15')];

        $this->assertSame(3, CareScheduleResolver::intervalForType(3, $weeklyGaps));
    }

    public function test_interval_for_type_derives_the_median_when_no_override_is_set(): void
    {
        $weeklyGaps = [Carbon::parse('2026-01-01'), Carbon::parse('2026-01-08'), Carbon::parse('2026-01-15')];

        $this->assertSame(7, CareScheduleResolver::intervalForType(null, $weeklyGaps));
    }

    public function test_interval_for_type_is_null_without_an_override_or_enough_history(): void
    {
        $this->assertNull(CareScheduleResolver::intervalForType(null, [Carbon::parse('2026-01-01')]));
    }
}
