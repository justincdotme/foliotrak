<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $care_event_id
 * @property string|null $soil_recipe
 * @property string|null $pot_size_value
 * @property string|null $pot_size_unit
 * @property bool        $fertilizer_added
 */
#[Fillable([
    'care_event_id',
    'soil_recipe',
    'pot_size_value',
    'pot_size_unit',
    'fertilizer_added',
])]
class RepottingDetail extends Model
{
    /** @var boolean Disable auto-increment */
    public $incrementing = false;

    /** @var boolean Disable timestamps */
    public $timestamps = false;

    /** @var string Primary key column */
    protected $primaryKey = 'care_event_id';

    /** @var string Primary key type */
    protected $keyType = 'int';

    /**
     * @return BelongsTo<CareEvent, $this>
     */
    public function careEvent(): BelongsTo
    {
        return $this->belongsTo(CareEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pot_size_value'   => 'decimal:1',
            'fertilizer_added' => 'boolean',
        ];
    }
}
