<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GrowthRate;
use Database\Factories\ObservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property GrowthRate|null $growth_rate
 */
#[Fillable([
    'care_event_id',
    'overall_health',
    'health_note',
    'light_level',
    'growth_rate',
    'growth_note',
    'leaf_size_mm',
    'weight_grams',
])]
class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory;

    protected $primaryKey = 'care_event_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'overall_health' => 'integer',
            'light_level' => 'integer',
            'growth_rate' => GrowthRate::class,
            'leaf_size_mm' => 'decimal:1',
            'weight_grams' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CareEvent, $this>
     */
    public function careEvent(): BelongsTo
    {
        return $this->belongsTo(CareEvent::class);
    }

    /**
     * @return BelongsToMany<Symptom, $this>
     */
    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'observation_symptoms', 'observation_id', 'symptom_id');
    }
}
