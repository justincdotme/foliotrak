<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GrowthRate;
use App\Enums\SoilMoistureLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int                    $care_event_id
 * @property int|null               $overall_health
 * @property string|null            $health_note
 * @property int|null               $light_level
 * @property GrowthRate|null        $growth_rate
 * @property string|null            $growth_note
 * @property string|null            $leaf_size_mm
 * @property int|null               $weight_grams
 * @property int|null               $ambient_humidity_pct
 * @property string|null            $ambient_temp_c
 * @property SoilMoistureLevel|null $soil_moisture_relative
 * @property int|null               $soil_moisture_precise
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
    'ambient_humidity_pct',
    'ambient_temp_c',
    'soil_moisture_relative',
    'soil_moisture_precise',
])]
class Observation extends Model
{
    /** @use HasFactory<ObservationFactory> */
    use HasFactory;

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
     * @return BelongsToMany<Symptom, $this>
     */
    public function symptoms(): BelongsToMany
    {
        return $this->belongsToMany(Symptom::class, 'observation_symptoms', 'observation_id', 'symptom_id');
    }

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'overall_health'         => 'integer',
            'light_level'            => 'integer',
            'growth_rate'            => GrowthRate::class,
            'leaf_size_mm'           => 'decimal:1',
            'weight_grams'           => 'integer',
            'ambient_humidity_pct'   => 'integer',
            'ambient_temp_c'         => 'decimal:1',
            'soil_moisture_relative' => SoilMoistureLevel::class,
            'soil_moisture_precise'  => 'integer',
        ];
    }
}
