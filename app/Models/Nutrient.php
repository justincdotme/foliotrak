<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'symbol', 'sort_order'])]
class Nutrient extends Model
{
    public $timestamps = false;
}
