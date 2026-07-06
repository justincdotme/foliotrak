<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SymptomCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int             $id
 * @property SymptomCategory $category
 * @property string          $key
 * @property string          $label
 * @property int             $sort_order
 * @property bool            $is_custom
 */
#[Fillable(['category', 'key', 'label', 'sort_order', 'is_custom'])]
class Symptom extends Model
{
    /** @use HasFactory<SymptomFactory> */
    use HasFactory;

    /** Brown leaf tips */
    public const string KEY_BROWN_TIPS = 'brown_tips';

    /** Leaf spots or discoloration */
    public const string KEY_LEAF_SPOTS = 'leaf_spots';

    /** Root rot */
    public const string KEY_ROOT_ROT = 'root_rot';

    /** Root-bound condition */
    public const string KEY_ROOT_BOUND = 'root_bound';

    /** @var boolean Disable timestamps */
    public $timestamps = false;

    /**
     * Find-or-create a freetext symptom. Slugging on the key collapses case
     * and punctuation variants onto one row and reuses a seeded row when a custom
     * label slugs to a seeded key, so custom entries stay joinable for correlation.
     *
     * @param string $label
     *
     * @return self
     */
    public static function findOrCreateCustom(string $label): self
    {
        return static::firstOrCreate(
            ['key' => static::slugFor($label)],
            ['category' => SymptomCategory::Custom, 'label' => $label, 'sort_order' => 99, 'is_custom' => true],
        );
    }

    /**
     * @param string $label
     *
     * @return string
     */
    public static function slugFor(string $label): string
    {
        $slug = Str::of($label)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_');

        return (string) Str::of((string) $slug)->substr(0, 48)->trim('_');
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'category'   => SymptomCategory::class,
            'sort_order' => 'integer',
            'is_custom'  => 'boolean',
        ];
    }
}
