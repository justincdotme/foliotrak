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
class Equipment extends Model
{
    /** @var boolean Disable timestamps */
    public $timestamps = false;

    /** @var string Table name */
    protected $table = 'equipment';
}
