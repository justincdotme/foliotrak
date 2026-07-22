<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                        $id
 * @property int                        $plant_id
 * @property int|null                   $care_event_id
 * @property string                     $disk
 * @property string                     $path
 * @property string|null                $thumb_path
 * @property string|null                $original_filename
 * @property \Illuminate\Support\Carbon $taken_on
 * @property string|null                $caption
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'plant_id',
    'care_event_id',
    'disk',
    'path',
    'thumb_path',
    'original_filename',
    'taken_on',
    'caption',
])]
class Photo extends Model
{
    /** @use HasFactory<PhotoFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Plant, $this>
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
        ];
    }
}
