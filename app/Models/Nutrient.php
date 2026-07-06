<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $id
 * @property string      $key
 * @property string      $label
 * @property string|null $symbol
 * @property int         $sort_order
 */
#[Fillable(['key', 'label', 'symbol', 'sort_order'])]
class Nutrient extends Model
{
    /** @var boolean Disable timestamps */
    public $timestamps = false;
}
