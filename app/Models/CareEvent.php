<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int                        $id
 * @property int                        $plant_id
 * @property int                        $care_event_type_id
 * @property \Illuminate\Support\Carbon $occurred_at
 * @property int|null                   $logged_by_user_id
 * @property string|null                $note
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable(['plant_id', 'care_event_type_id', 'occurred_at', 'logged_by_user_id', 'note'])]
class CareEvent extends Model
{
    /** @use HasFactory<CareEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Plant, $this>
     */
    public function plant(): BelongsTo
    {
        return $this->belongsTo(Plant::class);
    }

    /**
     * @return BelongsTo<CareEventType, $this>
     */
    public function careEventType(): BelongsTo
    {
        return $this->belongsTo(CareEventType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_user_id');
    }

    /**
     * @return HasOne<WateringDetail, $this>
     */
    public function watering(): HasOne
    {
        return $this->hasOne(WateringDetail::class);
    }

    /**
     * @return HasOne<FertilizingDetail, $this>
     */
    public function fertilizing(): HasOne
    {
        return $this->hasOne(FertilizingDetail::class);
    }

    /**
     * @return HasOne<RepottingDetail, $this>
     */
    public function repotting(): HasOne
    {
        return $this->hasOne(RepottingDetail::class);
    }

    /**
     * @return HasOne<Observation, $this>
     */
    public function observation(): HasOne
    {
        return $this->hasOne(Observation::class);
    }

    /**
     * @return HasOne<RelocationDetail, $this>
     */
    public function relocation(): HasOne
    {
        return $this->hasOne(RelocationDetail::class);
    }

    /**
     * @return HasOne<EquipmentChangeDetail, $this>
     */
    public function equipmentChange(): HasOne
    {
        return $this->hasOne(EquipmentChangeDetail::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
