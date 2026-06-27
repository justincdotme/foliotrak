<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LocationHealthInsight;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class LocationHealthInsightTest extends TestCase
{
    private Carbon $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = Carbon::parse('2026-04-01 12:00:00');
    }

    public function test_no_relocations_assigns_all_observations_to_current_location(): void
    {
        $result = LocationHealthInsight::forPlant(
            [],
            [$this->obs(-30, 5), $this->obs(-20, 3)],
            'living room',
        );

        $this->assertCount(1, $result);
        $this->assertSame('living room', $result[0]['location']);
        $this->assertSame(2, $result[0]['sample_size']);
        $this->assertSame(4.0, $result[0]['median_health']);
        $this->assertSame([5, 3], $result[0]['healths']);
    }

    public function test_observation_strictly_before_first_relocation_uses_from_location(): void
    {
        $result = LocationHealthInsight::forPlant(
            [['date' => $this->base->copy(), 'from' => 'shelf', 'to' => 'window']],
            [$this->obs(-1, 7)],
            'window',
        );

        $this->assertCount(1, $result);
        $this->assertSame('shelf', $result[0]['location']);
        $this->assertSame(7.0, $result[0]['median_health']);
    }

    public function test_observation_at_relocation_time_uses_to_location(): void
    {
        // Boundary: occurred_at == relocation occurred_at is NOT strictly before, so it belongs
        // to to_location (spec: strictly before uses from_location, else uses latest to_location
        // whose occurred_at <= t).
        $movedAt = $this->base->copy();

        $result = LocationHealthInsight::forPlant(
            [['date' => $movedAt, 'from' => 'shelf', 'to' => 'window']],
            [['date' => $movedAt->copy(), 'health' => 5]],
            'window',
        );

        $this->assertCount(1, $result);
        $this->assertSame('window', $result[0]['location']);
    }

    public function test_multiple_relocations_produce_multiple_buckets_with_correct_stats(): void
    {
        $firstMove = Carbon::parse('2026-02-01 12:00:00');
        $secondMove = Carbon::parse('2026-04-01 12:00:00');

        $result = LocationHealthInsight::forPlant(
            [
                ['date' => $firstMove, 'from' => 'hallway', 'to' => 'kitchen'],
                ['date' => $secondMove, 'from' => 'kitchen', 'to' => 'bedroom'],
            ],
            [
                ['date' => Carbon::parse('2026-01-15'), 'health' => 5],
                ['date' => Carbon::parse('2026-02-15'), 'health' => 3],
                ['date' => Carbon::parse('2026-02-20'), 'health' => 4],
                ['date' => Carbon::parse('2026-04-15'), 'health' => 7],
            ],
            'bedroom',
        );

        // kitchen: sample_size=2; bedroom and hallway: sample_size=1 each, sorted alpha
        $this->assertCount(3, $result);

        $this->assertSame('kitchen', $result[0]['location']);
        $this->assertSame(2, $result[0]['sample_size']);
        $this->assertSame(3.5, $result[0]['median_health']);
        $this->assertSame([3, 4], $result[0]['healths']);

        $this->assertSame('bedroom', $result[1]['location']);
        $this->assertSame(1, $result[1]['sample_size']);
        $this->assertSame([7], $result[1]['healths']);

        $this->assertSame('hallway', $result[2]['location']);
        $this->assertSame(1, $result[2]['sample_size']);
        $this->assertSame([5], $result[2]['healths']);
    }

    public function test_null_current_location_with_no_relocations_creates_null_bucket(): void
    {
        $result = LocationHealthInsight::forPlant(
            [],
            [$this->obs(-10, 3)],
            null,
        );

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['location']);
        $this->assertSame(3.0, $result[0]['median_health']);
        $this->assertSame(1, $result[0]['sample_size']);
        $this->assertSame([3], $result[0]['healths']);
    }

    public function test_null_from_location_creates_null_bucket(): void
    {
        $result = LocationHealthInsight::forPlant(
            [['date' => $this->base->copy(), 'from' => null, 'to' => 'window']],
            [
                $this->obs(-5, 8),
                $this->obs(5, 2),
            ],
            'window',
        );

        $this->assertCount(2, $result);
        $this->assertSame('window', $result[0]['location']);
        $this->assertNull($result[1]['location']);
    }

    public function test_null_bucket_sorts_last_even_when_it_has_the_highest_sample_size(): void
    {
        // 3 observations before the move go to null (from=null), 1 goes to 'desk'.
        // Despite sample_size=3 for null vs 1 for desk, null must be last.
        $result = LocationHealthInsight::forPlant(
            [['date' => $this->base->copy(), 'from' => null, 'to' => 'desk']],
            [
                $this->obs(-10, 7),
                $this->obs(-8, 6),
                $this->obs(-3, 5),
                $this->obs(5, 2),
            ],
            'desk',
        );

        $this->assertCount(2, $result);
        $this->assertSame('desk', $result[0]['location']);
        $this->assertSame(1, $result[0]['sample_size']);
        $this->assertNull($result[1]['location']);
        $this->assertSame(3, $result[1]['sample_size']);
    }

    public function test_empty_observations_returns_empty_list(): void
    {
        $result = LocationHealthInsight::forPlant(
            [['date' => $this->base->copy(), 'from' => 'a', 'to' => 'b']],
            [],
            'b',
        );

        $this->assertSame([], $result);
    }

    /**
     * @return array{date: Carbon, health: int}
     */
    private function obs(int $daysFromBase, int $health): array
    {
        return ['date' => $this->base->copy()->addDays($daysFromBase), 'health' => $health];
    }
}
