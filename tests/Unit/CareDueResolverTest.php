<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\CareDueResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CareDueResolverTest extends TestCase
{
    #[DataProvider('statusCases')]
    public function test_maps_days_left_to_a_care_status(int $daysLeft, string $expected): void
    {
        $this->assertSame($expected, CareDueResolver::status($daysLeft));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function statusCases(): iterable
    {
        // The droplet fills by time-to-due: past due is overdue, the day of and
        // the day before are due-soon, anything further out is ok. Boundaries
        // (-1/0 and 1/2) are the cases a threshold change would silently break.
        yield 'well past due reads as overdue' => [-10, 'overdue'];
        yield 'one day past due reads as overdue' => [-1, 'overdue'];
        yield 'due today reads as due-soon' => [0, 'due-soon'];
        yield 'due tomorrow reads as due-soon' => [1, 'due-soon'];
        yield 'two days out reads as ok' => [2, 'ok'];
        yield 'comfortably ahead reads as ok' => [30, 'ok'];
    }
}
