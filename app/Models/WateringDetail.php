<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int      $care_event_id
 * @property int|null $amount_ml
 */
#[Fillable(['care_event_id', 'amount_ml'])]
class WateringDetail extends Model
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
            'amount_ml' => 'integer',
        ];
    }
}
