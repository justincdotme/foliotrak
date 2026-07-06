<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int      $care_event_id
 * @property int|null $from_location_id
 * @property int|null $to_location_id
 */
#[Fillable(['care_event_id', 'from_location_id', 'to_location_id'])]
class RelocationDetail extends Model
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
     * @return BelongsTo<Location, $this>
     */
    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
