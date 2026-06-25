<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Starter location tags so the plant list filter and the tag picker work on a
     * fresh install. They mirror the prototype's defaults and stay user-editable.
     */
    public function run(): void
    {
        $tags = [
            ['name' => 'Living room', 'color' => 'var(--series-1)'],
            ['name' => 'Bright window', 'color' => 'var(--series-2)'],
            ['name' => 'Low light', 'color' => 'var(--series-3)'],
            ['name' => 'Office', 'color' => 'var(--series-4)'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }
    }
}
