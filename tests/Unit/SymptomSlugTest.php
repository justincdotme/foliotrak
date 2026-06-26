<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Symptom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SymptomSlugTest extends TestCase
{
    /**
     * The slug is the dedup key for freetext symptoms: case and punctuation
     * variants must collapse onto one key, and it must stay inside the 48-char
     * column, so a drift here would split or truncate reusable custom rows.
     */
    #[DataProvider('slugCases')]
    public function test_slugs_a_freetext_label(string $label, string $expected): void
    {
        $this->assertSame($expected, Symptom::slugFor($label));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function slugCases(): iterable
    {
        yield 'spaces become underscores' => ['Brown tips', 'brown_tips'];
        yield 'case is folded' => ['BROWN TIPS', 'brown_tips'];
        yield 'punctuation runs collapse to one underscore' => ['Leaf  spots!!', 'leaf_spots'];
        yield 'separators are trimmed' => ['  drooping  ', 'drooping'];
        yield 'slashes and pluses collapse' => ['A/B + C', 'a_b_c'];
        yield 'a seeded label slugs to its seeded key' => ['Root-bound', 'root_bound'];
        yield 'overlong labels truncate to the column width' => [
            str_repeat('a', 60),
            str_repeat('a', 48),
        ];
    }
}
