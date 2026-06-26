<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    protected $primaryKey = 'care_event_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'npk_n' => 'decimal:2',
            'npk_p' => 'decimal:2',
            'npk_k' => 'decimal:2',
            'dose_pct' => 'integer',
            'amount_ml' => 'integer',
        ];
    }

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
}
