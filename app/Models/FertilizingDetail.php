<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int         $care_event_id
 * @property int         $fertilizer_form_id
 * @property string|null $brand
 * @property string|null $product
 * @property string|null $npk_n
 * @property string|null $npk_p
 * @property string|null $npk_k
 * @property int|null    $dose_pct
 * @property int|null    $amount_ml
 */
#[Fillable([
    'care_event_id',
    'fertilizer_form_id',
    'brand',
    'product',
    'npk_n',
    'npk_p',
    'npk_k',
    'dose_pct',
    'amount_ml',
])]
class FertilizingDetail extends Model
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
     * @return BelongsTo<FertilizerForm, $this>
     */
    public function fertilizerForm(): BelongsTo
    {
        return $this->belongsTo(FertilizerForm::class);
    }

    /**
     * @return HasMany<FertilizingNutrient, $this>
     */
    public function nutrients(): HasMany
    {
        return $this->hasMany(FertilizingNutrient::class, 'care_event_id', 'care_event_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'npk_n'     => 'decimal:2',
            'npk_p'     => 'decimal:2',
            'npk_k'     => 'decimal:2',
            'dose_pct'  => 'integer',
            'amount_ml' => 'integer',
        ];
    }
}
