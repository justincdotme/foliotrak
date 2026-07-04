<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SymptomCategory;
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

    public const string KEY_BROWN_TIPS = 'brown_tips';

    public const string KEY_LEAF_SPOTS = 'leaf_spots';

    public const string KEY_ROOT_ROT = 'root_rot';

    public const string KEY_ROOT_BOUND = 'root_bound';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'category' => SymptomCategory::class,
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
            ['category' => SymptomCategory::Custom, 'label' => $label, 'sort_order' => 99, 'is_custom' => true],
        );
    }

    public static function slugFor(string $label): string
    {
        $slug = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return (string) Str::of((string) $slug)->substr(0, 48)->trim('_');
    }
}
