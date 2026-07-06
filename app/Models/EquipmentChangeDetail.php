<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int      $care_event_id
 * @property int|null $equipment_id
 * @property string   $equipment_label
 * @property string   $action
 */
#[Fillable(['care_event_id', 'equipment_id', 'equipment_label', 'action'])]
class EquipmentChangeDetail extends Model
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
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
