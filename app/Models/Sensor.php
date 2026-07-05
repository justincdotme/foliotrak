<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'mac',
    'device_name',
    'name',
    'color',
    'location',
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
}
