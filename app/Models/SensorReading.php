<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                        $id
 * @property int                        $sensor_id
 * @property float                      $temperature
 * @property float                      $humidity
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property array<string, mixed>|null  $meta
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'sensor_id',
    'temperature',
    'humidity',
    'recorded_at',
    'meta',
])]
class SensorReading extends Model
{
    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'humidity'    => 'float',
            'recorded_at' => 'datetime',
            'meta'        => 'array',
        ];
    }
}
