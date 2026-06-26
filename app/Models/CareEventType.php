<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'sort_order'])]
class CareEventType extends Model
{
    public $timestamps = false;

    /**
     * The id backing a seeded type key, used when creating typed events.
     */
    public static function idFor(string $key): int
    {
        return (int) static::query()->where('key', $key)->value('id');
    }
}
