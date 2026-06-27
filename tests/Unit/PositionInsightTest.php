<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PositionInsight;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PositionInsightTest extends TestCase
{
    private Carbon $movedAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->movedAt = Carbon::parse('2026-05-01 12:00:00');
    }

    public function test_a_move_with_readings_on_both_sides_summarizes_before_and_after(): void
    {
        $insights = PositionInsight::forMoves(
            [['date' => $this->movedAt->copy(), 'from' => $this->loc(1, 'shelf'), 'to' => $this->loc(2, 'kitchen window')]],
            [
                $this->obs(-20, 4), $this->obs(-10, 4),
                $this->obs(5, 2), $this->obs(12, 2), $this->obs(20, 3),
            ],
        );

        $this->assertCount(1, $insights);
        $insight = $insights[0];

        $this->assertSame('2026-05-01', $insight['moved_on']);
        $this->assertSame('shelf', $insight['from_location']['name']);
        $this->assertSame('kitchen window', $insight['to_location']['name']);
        $this->assertSame(4.0, $insight['health_before']['median']);
        $this->assertSame(2, $insight['health_before']['sample_size']);
        $this->assertSame(2.0, $insight['health_after']['median']);
        $this->assertSame(3, $insight['health_after']['sample_size']);
    }

    public function test_a_move_with_readings_on_only_one_side_is_skipped(): void
    {
        $afterOnly = PositionInsight::forMoves(
            [['date' => $this->movedAt->copy(), 'from' => null, 'to' => $this->loc(1, 'desk')]],
            [$this->obs(5, 4), $this->obs(10, 5)],
        );
        $beforeOnly = PositionInsight::forMoves(
            [['date' => $this->movedAt->copy(), 'from' => null, 'to' => $this->loc(1, 'desk')]],
            [$this->obs(-5, 4), $this->obs(-10, 5)],
        );

        $this->assertSame([], $afterOnly);
        $this->assertSame([], $beforeOnly);
    }

    public function test_readings_outside_the_four_week_window_are_excluded(): void
    {
        $insights = PositionInsight::forMoves(
            [['date' => $this->movedAt->copy(), 'from' => $this->loc(1, 'a'), 'to' => $this->loc(2, 'b')]],
            [
                $this->obs(-40, 1), // older than four weeks, excluded
                $this->obs(-10, 4),
                $this->obs(10, 2),
                $this->obs(40, 5),  // later than four weeks, excluded
            ],
        );

        $this->assertCount(1, $insights);
        $this->assertSame(1, $insights[0]['health_before']['sample_size']);
        $this->assertSame(1, $insights[0]['health_after']['sample_size']);
    }

    public function test_each_qualifying_move_yields_its_own_insight(): void
    {
        $first = Carbon::parse('2026-03-01 12:00:00');
        $second = Carbon::parse('2026-05-01 12:00:00');

        $insights = PositionInsight::forMoves(
            [
                ['date' => $first->copy(), 'from' => $this->loc(1, 'a'), 'to' => $this->loc(2, 'b')],
                ['date' => $second->copy(), 'from' => $this->loc(2, 'b'), 'to' => $this->loc(3, 'c')],
            ],
            [
                ['date' => $first->copy()->subDays(5), 'health' => 5],
                ['date' => $first->copy()->addDays(5), 'health' => 4],
                ['date' => $second->copy()->subDays(5), 'health' => 4],
                ['date' => $second->copy()->addDays(5), 'health' => 2],
            ],
        );

        $this->assertCount(2, $insights);
        $this->assertSame('2026-03-01', $insights[0]['moved_on']);
        $this->assertSame('2026-05-01', $insights[1]['moved_on']);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function loc(int $id, string $name): array
    {
        return ['id' => $id, 'name' => $name];
    }

    /**
     * @return array{date: Carbon, health: int}
     */
    private function obs(int $daysFromMove, int $health): array
    {
        return ['date' => $this->movedAt->copy()->addDays($daysFromMove), 'health' => $health];
    }
}
