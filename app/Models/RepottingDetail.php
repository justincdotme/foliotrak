<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'care_event_id',
    'soil_recipe',
    'pot_size_value',
    'pot_size_unit',
    'fertilizer_added',
])]
class RepottingDetail extends Model
{
    protected $primaryKey = 'care_event_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'pot_size_value' => 'decimal:1',
            'fertilizer_added' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CareEvent, $this>
     */
    public function careEvent(): BelongsTo
    {
        return $this->belongsTo(CareEvent::class);
    }
}
