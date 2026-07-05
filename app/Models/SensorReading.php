<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'sensor_id',
    'temperature',
    'humidity',
    'recorded_at',
    'meta',
])]
class SensorReading extends Model
{
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'humidity' => 'float',
            'recorded_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Sensor, $this>
     */
    public function sensor(): BelongsTo
    {
        return $this->belongsTo(Sensor::class);
    }
}
