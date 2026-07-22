<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int         $id
 * @property int         $care_event_id
 * @property int         $nutrient_id
 * @property string|null $note
 */
#[Fillable(['care_event_id', 'nutrient_id', 'note'])]
class FertilizingNutrient extends Model
{
    /** @var boolean Disable timestamps */
    public $timestamps = false;

    /**
     * @return BelongsTo<Nutrient, $this>
     */
    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(Nutrient::class);
    }
}
