<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SensorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                        $id
 * @property string                     $mac
 * @property string|null                $device_name
 * @property string                     $name
 * @property string                     $color
 * @property string|null                $location
 * @property SensorType                 $type
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'mac',
    'device_name',
    'name',
    'color',
    'location',
    'type',
])]
class Sensor extends Model
{
    /**
     * @return BelongsToMany<Plant, $this>
     */
    public function plants(): BelongsToMany
    {
        return $this->belongsToMany(Plant::class, 'plant_sensor');
    }

    /**
     * @return HasMany<SensorReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    /**
     * @return array<string, class-string>
     */
    protected function casts(): array
    {
        return [
            'type' => SensorType::class,
        ];
    }
}
