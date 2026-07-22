<?php

declare(strict_types=1);

namespace Tests\Unit\Care;

use App\Support\Care\DueStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DueStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{int, DueStatus}>
     */
    public static function statusCases(): iterable
    {
        yield 'past due is overdue' => [-3, DueStatus::Overdue];
        yield 'one day past is overdue' => [-1, DueStatus::Overdue];
        yield 'due today is due-soon' => [0, DueStatus::DueSoon];
        yield 'due tomorrow is due-soon' => [1, DueStatus::DueSoon];
        yield 'two days out is ok' => [2, DueStatus::Ok];
        yield 'a month out is ok' => [30, DueStatus::Ok];
    }

    /**
     * @param integer   $daysLeft
     * @param DueStatus $expected
     *
     * @return void
     */
    #[DataProvider('statusCases')]
    public function test_maps_days_left_to_a_care_status(int $daysLeft, DueStatus $expected): void
    {
        $this->assertSame($expected, DueStatus::fromDaysLeft($daysLeft));
    }
}
