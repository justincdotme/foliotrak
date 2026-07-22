<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                        $id
 * @property int                        $sensor_id
 * @property array<string, mixed>       $data
 * @property \Illuminate\Support\Carbon $recorded_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
#[Fillable([
    'sensor_id',
    'data',
    'recorded_at',
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
            'data'        => 'array',
            'recorded_at' => 'datetime',
        ];
    }
}
