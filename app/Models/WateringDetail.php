<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['care_event_id', 'amount_ml'])]
class WateringDetail extends Model
{
    protected $primaryKey = 'care_event_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'amount_ml' => 'integer',
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
