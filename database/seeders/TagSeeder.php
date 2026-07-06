<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Retired tag names removed during seeding.
     *
     * @var list<string>
     */
    private const RETIRED = ['Living room', 'Bright window', 'Low light', 'Office'];

    /** @return void */
    public function run(): void
    {
        Tag::whereIn('name', self::RETIRED)->delete();

        $tags = [
            ['name' => 'Tropical', 'color' => 'var(--series-1)'],
            ['name' => 'Succulent', 'color' => 'var(--series-2)'],
            ['name' => 'Trailing', 'color' => 'var(--series-3)'],
            ['name' => 'Pet-safe', 'color' => 'var(--series-4)'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }
    }
}
