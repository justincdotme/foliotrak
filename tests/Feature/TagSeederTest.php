<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_starter_tags(): void
    {
        $this->seed(TagSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['Living room', 'Bright window', 'Low light', 'Office'],
            Tag::query()->pluck('name')->all(),
        );
    }

    public function test_is_idempotent_so_re_seeding_on_boot_adds_no_duplicates(): void
    {
        $this->seed(TagSeeder::class);
        $this->seed(TagSeeder::class);

        $this->assertSame(4, Tag::query()->count());
    }
}
