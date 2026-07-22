<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $id
 * @property string $key
 * @property string $label
 * @property int    $sort_order
 */
#[Fillable(['key', 'label', 'sort_order'])]
class CareEventType extends Model
{
    /** @var boolean Disable timestamps */
    public $timestamps = false;

    /**
     * The id backing a seeded type key, used when creating typed events.
     *
     * @param string $key
     *
     * @return integer
     */
    public static function idFor(string $key): int
    {
        return (int) static::query()->where('key', $key)->value('id');
    }
}
