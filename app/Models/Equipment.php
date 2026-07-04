<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'sort_order'])]
class Equipment extends Model
{
    public $timestamps = false;

    protected $table = 'equipment';
}
