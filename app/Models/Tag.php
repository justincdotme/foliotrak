<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int                        $id
 * @property string                     $name
 * @property string|null                $color
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'name',
    'color',
])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /** @var string Table name */
    protected $table = 'plant_tags';

    /**
     * @return BelongsToMany<Plant, $this>
     */
    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_tag');
    }
}
