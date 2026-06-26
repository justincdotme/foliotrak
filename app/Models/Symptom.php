<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SymptomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['category', 'key', 'label', 'sort_order', 'is_custom'])]
class Symptom extends Model
{
    /** @use HasFactory<SymptomFactory> */
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_custom' => 'boolean',
        ];
    }

    /**
     * Find-or-create a freetext symptom. Slugging on the key collapses case
     * and punctuation variants onto one row and reuses a seeded row when a custom
     * label slugs to a seeded key, so custom entries stay joinable for correlation.
     */
    public static function findOrCreateCustom(string $label): self
    {
        return static::firstOrCreate(
            ['key' => static::slugFor($label)],
            ['category' => 'custom', 'label' => $label, 'sort_order' => 99, 'is_custom' => true],
        );
    }

    public static function slugFor(string $label): string
    {
        $slug = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return (string) Str::of((string) $slug)->substr(0, 48)->trim('_');
    }
}
